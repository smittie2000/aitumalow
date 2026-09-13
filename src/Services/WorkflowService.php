<?php

namespace Aitumalow\Services;

use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\RunCommand;
use Aitumalow\DTOs\StateWorkflowDefinition;
use Aitumalow\DTOs\WorkflowBlueprint;
use Aitumalow\DTOs\WorkflowStart;
use Aitumalow\Engine\GraphValidator;
use Aitumalow\Enums\CommandStatus;
use Aitumalow\Enums\CreatedVia;
use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Exceptions\WorkflowCommandDidNotComplete;
use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowCommand;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowRevision;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Registry\WorkflowSubjectRegistry;
use Aitumalow\Runtime\DurableWorkflowRuntime;
use Aitumalow\Runtime\ScheduleSynchronizer;
use Aitumalow\Runtime\WorkflowSnapshot;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/** @phpstan-type WorkflowItems array<int, array<string, mixed>> */
class WorkflowService
{
    public function __construct(
        private readonly DurableWorkflowRuntime $runtime,
        private readonly GraphValidator $validator,
        private readonly WorkflowNodeConfigValidator $configValidator,
        private readonly ExecutionScopeResolver $scopeResolver,
        private readonly ScheduleSynchronizer $schedules,
        private readonly WorkflowSnapshot $snapshots,
        private readonly WorkflowSubjectRegistry $subjects,
    ) {}

    // ── Execution ──────────────────────────────────────────────────

    public function findByKey(string $key): Workflow
    {
        return Workflow::query()->where('key', $key)->firstOrFail();
    }

    /**
     * Install and activate a host-defined product state machine.
     *
     * This is the high-level wrapper seam for applications that need states
     * and commands but must not build graph rows or interact with Durable.
     */
    public function installStateWorkflow(
        StateWorkflowDefinition $definition,
        ?string $principalReference = null,
    ): WorkflowRevision {
        return DB::transaction(function () use ($definition, $principalReference): WorkflowRevision {
            $hash = hash('sha256', json_encode(
                $this->canonicalize($definition->toArray()),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            ));
            $workflow = Workflow::withTrashed()->where('key', $definition->key)->lockForUpdate()->first();

            if (! $workflow instanceof Workflow) {
                $workflow = Workflow::query()->create([
                    'key' => $definition->key,
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'is_active' => false,
                    'created_via' => CreatedVia::Code,
                    'settings' => [],
                ]);
            } elseif ($workflow->trashed()) {
                $workflow->restore();
            }

            if (data_get($workflow->settings, 'state_workflow.definition_hash') === $hash
                && $workflow->active_revision_id !== null) {
                return $workflow->activeRevision()->firstOrFail();
            }

            $workflow->edges()->delete();
            $workflow->nodes()->delete();
            $workflow->update([
                'name' => $definition->name,
                'description' => $definition->description,
                'is_active' => false,
                'settings' => array_replace_recursive($definition->settings, [
                    'subject_type' => $definition->subjectType,
                    'state_workflow' => [
                        'definition_hash' => $hash,
                        'initial_state' => $definition->initialState,
                        'transition_capability' => $definition->transitionCapability,
                    ],
                ]),
            ]);

            $trigger = $this->addNode($workflow, 'core.manual', name: 'Start');
            $stateNodes = [];
            $transitionsByState = collect($definition->transitions)->groupBy('fromState');

            foreach ($definition->states as $state) {
                $commands = $transitionsByState->get($state->key, collect())
                    ->map(static fn ($transition): array => [
                        'key' => $transition->key,
                        'label' => $transition->label,
                        'target_state' => $transition->targetState,
                        'metadata' => $transition->metadata,
                    ])
                    ->values()
                    ->all();
                $stateNodes[$state->key] = $this->addNode($workflow, 'core.wait_resume', [
                    'state_key' => $state->key,
                    'metadata' => $state->metadata,
                    'commands' => $commands,
                ], $state->label);
            }

            $this->connect($trigger, $stateNodes[$definition->initialState]);

            foreach ($definition->transitions as $transition) {
                $action = $this->addNode($workflow, $definition->transitionCapability, [
                    'transition' => [
                        'key' => $transition->key,
                        'label' => $transition->label,
                        'from_state' => $transition->fromState,
                        'target_state' => $transition->targetState,
                        'metadata' => $transition->metadata,
                    ],
                ], $transition->label);
                $this->connect($stateNodes[$transition->fromState], $action, $transition->key);

                if ($transition->targetState !== '@previous') {
                    $this->connect($action, $stateNodes[$transition->targetState]);

                    continue;
                }

                $switch = $this->addNode($workflow, 'core.switch', [
                    'field' => 'target_state',
                    'cases' => array_map(static fn ($state): array => [
                        'port' => 'case_'.$state->key,
                        'operator' => 'equals',
                        'value' => $state->key,
                    ], $definition->states),
                    'fallthrough' => false,
                ], $transition->label.' target');
                $this->connect($action, $switch);
                foreach ($definition->states as $state) {
                    $this->connect($switch, $stateNodes[$state->key], 'case_'.$state->key);
                }
            }

            $revision = $this->publish($workflow, $principalReference);
            $this->activate($workflow, $revision);

            return $revision;
        });
    }

    /** Install and activate a graph using stable host aliases instead of node IDs. */
    public function installWorkflow(
        WorkflowBlueprint $blueprint,
        ?string $principalReference = null,
    ): WorkflowRevision {
        return DB::transaction(function () use ($blueprint, $principalReference): WorkflowRevision {
            $hash = hash('sha256', json_encode(
                $this->canonicalize($blueprint->toArray()),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            ));
            $workflow = Workflow::withTrashed()->where('key', $blueprint->key)->lockForUpdate()->first();

            if (! $workflow instanceof Workflow) {
                $workflow = Workflow::query()->create([
                    'key' => $blueprint->key,
                    'name' => $blueprint->name,
                    'description' => $blueprint->description,
                    'is_active' => false,
                    'created_via' => CreatedVia::Code,
                    'settings' => [],
                ]);
            } elseif ($workflow->trashed()) {
                $workflow->restore();
            }

            if (data_get($workflow->settings, 'blueprint.definition_hash') === $hash
                && $workflow->active_revision_id !== null) {
                return $workflow->activeRevision()->firstOrFail();
            }

            $workflow->edges()->delete();
            $workflow->nodes()->delete();
            $workflow->update([
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'is_active' => false,
                'settings' => array_replace_recursive($blueprint->settings, [
                    'subject_type' => $blueprint->subjectType,
                    'blueprint' => ['definition_hash' => $hash],
                ]),
            ]);

            $nodes = [];
            foreach ($blueprint->nodes as $node) {
                $nodes[$node['id']] = $this->addNode(
                    $workflow,
                    $node['capability'],
                    $node['config'],
                    $node['name'] ?? $node['id'],
                );
            }
            foreach ($blueprint->edges as $edge) {
                $this->connect(
                    $nodes[$edge['from']],
                    $nodes[$edge['to']],
                    $edge['output'],
                    $edge['input'],
                );
            }

            $revision = $this->publish($workflow, $principalReference);
            $this->activate($workflow, $revision);

            return $revision;
        });
    }

    /**
     * Start a durable workflow execution.
     *
     * @param  WorkflowItems  $payload
     */
    public function run(int|Workflow $workflow, array $payload = []): WorkflowRun
    {
        return $this->start($workflow, new WorkflowStart(payload: $payload));
    }

    /**
     * Start through Aitumalow's host-facing workflow contract.
     */
    public function start(int|Workflow $workflow, WorkflowStart $command): WorkflowRun
    {
        $workflow = $this->resolveWorkflow($workflow);

        if (! $workflow->is_active || $workflow->active_revision_id === null) {
            throw new LogicException("Workflow {$workflow->id} has no active published revision.");
        }

        $revision = $workflow->activeRevision()->firstOrFail();
        $scope = $command->scope ?? $this->scopeResolver->resolve($workflow);
        $subjectType = null;
        $subjectReference = null;
        $subjectContext = [];
        $subjectFreshness = null;
        $configuredSubject = data_get($revision->definition, 'settings.subject_type');

        if (is_string($configuredSubject) && $configuredSubject !== '' && $command->subject === null) {
            throw new LogicException(
                "Workflow revision {$revision->id} requires subject [{$configuredSubject}].",
            );
        }

        if ($command->subject !== null) {
            $adapter = $this->subjects->get($command->subject->type);
            $subject = $adapter->resolve($command->subject->reference);

            if (! $adapter->canStart($scope, $subject)) {
                throw new AuthorizationException('The workflow subject cannot be started in this execution scope.');
            }

            if ($configuredSubject !== $adapter->key()) {
                throw new LogicException(
                    "Workflow revision {$revision->id} expects subject [{$configuredSubject}], not [{$adapter->key()}].",
                );
            }

            $allowed = $adapter->allowedCapabilities($subject);
            foreach ($revision->definition['nodes'] ?? [] as $node) {
                $key = is_array($node) ? ($node['key'] ?? null) : null;
                $type = is_array($node) ? ($node['type'] ?? null) : null;
                if ($type === NodeType::Action->value && is_string($key) && ! str_starts_with($key, 'core.') && ! in_array($key, $allowed, true)) {
                    throw new AuthorizationException("Subject [{$adapter->key()}] does not allow capability [{$key}].");
                }
            }

            $subjectType = $adapter->key();
            $subjectReference = $adapter->reference($subject);
            $subjectContext = $adapter->context($subject);
            $subjectFreshness = $adapter->freshness($subject);
            json_encode($subjectContext, JSON_THROW_ON_ERROR);
        }

        $idempotencyScope = $command->idempotencyKey === null
            ? null
            : ($command->idempotencyScope ?? $this->defaultIdempotencyScope($workflow, $scope));

        return $this->runtime->start(
            workflow: $workflow,
            revision: $revision,
            payload: $command->payload,
            scope: $scope,
            subjectType: $subjectType,
            subjectReference: $subjectReference,
            subjectContext: $subjectContext,
            subjectFreshness: $subjectFreshness,
            executorReference: $command->executorReference,
            idempotencyScope: $idempotencyScope,
            idempotencyKey: $command->idempotencyKey,
        );
    }

    /**
     * Resume a waiting workflow run.
     *
     * @param  WorkflowItems  $payload
     */
    public function resume(int|WorkflowRun $run, array $payload = []): WorkflowRun
    {
        $run = $this->resolveRun($run);
        $run = $this->runtime->synchronizeRun($run);
        if ($run->waiting_node_id === null) {
            throw new LogicException("Aitumalow run {$run->id} is not waiting for a command.");
        }
        $this->command($run, new RunCommand(
            name: 'resume',
            idempotencyKey: 'resume:'.Str::uuid()->toString(),
            payload: $payload[0] ?? [],
        ));

        return $run->fresh();
    }

    public function command(int|WorkflowRun $run, RunCommand $command): WorkflowCommand
    {
        return $this->dispatchCommand($run, $command, false);
    }

    /**
     * Submit a command and wait for its business effect to finish applying.
     *
     * This is the synchronous host seam for request/response operations. Hosts
     * remain insulated from the underlying durable update lifecycle.
     */
    public function commandAndWait(int|WorkflowRun $run, RunCommand $command): WorkflowCommand
    {
        $result = $this->dispatchCommand($run, $command, true);

        if ($result->status !== CommandStatus::Completed || $result->error_message !== null) {
            throw new WorkflowCommandDidNotComplete($result);
        }

        $this->runtime->synchronizeRun($result->run);

        return $result;
    }

    private function dispatchCommand(int|WorkflowRun $run, RunCommand $command, bool $waitForCompletion): WorkflowCommand
    {
        $run = $this->resolveRun($run);
        $scope = $command->scope ?? ExecutionScope::fromArray($run->execution_scope);
        $existing = $run->commands()->where('idempotency_key', $command->idempotencyKey)->first();

        if ($existing instanceof WorkflowCommand) {
            $this->authorizeSubjectCommand($run, $command, $scope);

            return $this->runtime->command(
                $run,
                $command,
                $scope,
                $existing->expected_node_id,
            );
        }

        $run = $this->runtime->synchronizeRun($run);
        if ($run->status !== RunStatus::Waiting) {
            throw new LogicException("Aitumalow run {$run->id} is not waiting for a command.");
        }
        $currentState = $this->runtime->currentState($run);
        $expectedNodeId = $currentState['waiting_node_id'];
        $waitingState = $currentState['waiting_state'];
        if ($expectedNodeId === null) {
            throw new LogicException("Aitumalow run {$run->id} is not waiting for a command.");
        }
        if ($command->expectedState !== null && $waitingState !== $command->expectedState) {
            throw new LogicException(
                "Aitumalow run {$run->id} is waiting in state [{$waitingState}], not [{$command->expectedState}].",
            );
        }

        $waitNode = null;
        $nodes = $run->revision->definition['nodes'] ?? null;
        if (is_array($nodes)) {
            foreach ($nodes as $node) {
                if (is_array($node) && (int) ($node['id'] ?? 0) === $expectedNodeId) {
                    $waitNode = $node;
                    break;
                }
            }
        }
        if (! is_array($waitNode) || ($waitNode['key'] ?? null) !== 'core.wait_resume') {
            throw new LogicException("Node {$expectedNodeId} is not an Aitumalow command wait.");
        }
        $acceptedCommands = $currentState['accepted_commands'];
        if (! in_array($command->name, $acceptedCommands, true)) {
            throw new LogicException("Command [{$command->name}] is not accepted in state [{$waitingState}].");
        }
        $effectNodeId = null;

        if ($waitForCompletion) {
            $edges = $run->revision->definition['edges'] ?? [];
            if (is_array($edges)) {
                foreach ($edges as $edge) {
                    if (is_array($edge)
                        && (int) ($edge['source_node_id'] ?? 0) === $expectedNodeId
                        && ($edge['source_port'] ?? null) === $command->name) {
                        $effectNodeId = (int) ($edge['target_node_id'] ?? 0);
                        break;
                    }
                }
            }

            if ($effectNodeId === null || $effectNodeId < 1) {
                throw new LogicException(
                    "Command [{$command->name}] has no business-effect route from wait node {$expectedNodeId}.",
                );
            }
        }

        $this->authorizeSubjectCommand($run, $command, $scope);

        return $this->runtime->command(
            $run,
            $command,
            $scope,
            $expectedNodeId,
            $waitForCompletion,
            $effectNodeId,
        );
    }

    private function authorizeSubjectCommand(
        WorkflowRun $run,
        RunCommand $command,
        ExecutionScope $scope,
    ): void {
        if ($run->subject_type === null || $run->subject_reference === null) {
            return;
        }

        if ($command->scope === null) {
            throw new LogicException('Commands for subject-bound workflows require the caller execution scope.');
        }

        $adapter = $this->subjects->get($run->subject_type);
        $subject = $adapter->resolve($run->subject_reference);
        if ($adapter->reference($subject) !== $run->subject_reference
            || ! $adapter->canCommand($scope, $subject, $command->name, $command->payload)) {
            throw new AuthorizationException('The workflow subject command is not authorized in this execution scope.');
        }
    }

    public function synchronizeCommand(int|WorkflowCommand $command): WorkflowCommand
    {
        $command = $command instanceof WorkflowCommand
            ? $command
            : WorkflowCommand::findOrFail($command);

        return $this->runtime->synchronizeCommand($command);
    }

    /**
     * Cancel a running or waiting workflow.
     */
    public function cancel(int|WorkflowRun $run): WorkflowRun
    {
        $run = $this->resolveRun($run);

        return $this->runtime->cancel($run);
    }

    /**
     * Replay a completed/failed run with its original payload.
     */
    public function replay(int|WorkflowRun $run): WorkflowRun
    {
        $run = $this->resolveRun($run);

        return $this->runtime->start(
            workflow: $run->workflow,
            revision: $run->revision,
            payload: $run->initial_payload ?? [],
            scope: ExecutionScope::fromArray($run->execution_scope),
            subjectType: $run->subject_type,
            subjectReference: $run->subject_reference,
            subjectContext: $run->subject_context ?? [],
            subjectFreshness: $run->subject_freshness,
            executorReference: $run->executor_reference,
        );
    }

    /**
     * Execute a workflow up to (and including) a specific node, then stop.
     *
     * @param  WorkflowItems  $payload
     */
    public function testNode(int|Workflow $workflow, int $nodeId, array $payload = [], ?string $expectedGraphHash = null): WorkflowRun
    {
        return $this->withGraphLock($this->resolveWorkflow($workflow), function (Workflow $workflow) use ($nodeId, $payload, $expectedGraphHash): WorkflowRun {
            $workflow->nodes()->findOrFail($nodeId);
            if ($expectedGraphHash !== null) {
                $snapshots = app(WorkflowGraphSnapshot::class);
                if (! hash_equals($expectedGraphHash, $snapshots->hash($snapshots->capture($workflow)))) {
                    throw new WorkflowDraftConflictException;
                }
            }
            $this->validator->validate($workflow);

            return $this->runtime->start(
                workflow: $workflow,
                revision: $this->publish($workflow),
                payload: $payload,
                scope: $this->scopeResolver->resolve($workflow),
                stopAfterNodeId: $nodeId,
            );
        });
    }

    // ── CRUD ───────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public function create(array $data): Workflow
    {
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $workflow = Workflow::create($data);

        if ($tagIds !== null) {
            $workflow->tags()->sync($tagIds);
            $workflow->load('tags');
        }

        return $workflow;
    }

    /** @param array<string, mixed> $data */
    public function update(int|Workflow $workflow, array $data): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $workflow->update($data);

        if ($tagIds !== null) {
            $workflow->tags()->sync($tagIds);
        }

        return $workflow->fresh();
    }

    public function delete(int|Workflow $workflow): void
    {
        $workflow = $this->resolveWorkflow($workflow);
        $this->schedules->delete($workflow);
        $workflow->delete();
    }

    public function duplicate(int|Workflow $workflow): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);

        $new = $workflow->replicate();
        $new->key = 'workflow_'.Str::lower((string) Str::ulid());
        $new->name = $workflow->name.' (Copy)';
        $new->is_active = false;
        $new->created_via = CreatedVia::Duplicate;
        $new->save();

        $nodeIdMap = [];
        foreach ($workflow->nodes as $node) {
            $newNode = $node->replicate();
            $newNode->workflow_id = $new->id;
            $newNode->save();
            $nodeIdMap[$node->id] = $newNode->id;
        }

        foreach ($workflow->edges as $edge) {
            $new->edges()->create([
                'source_node_id' => $nodeIdMap[$edge->source_node_id],
                'source_port' => $edge->source_port,
                'target_node_id' => $nodeIdMap[$edge->target_node_id],
                'target_port' => $edge->target_port,
            ]);
        }

        $new->tags()->sync($workflow->tags->pluck('id'));

        return $new->load(['nodes', 'edges', 'tags']);
    }

    // ── State ──────────────────────────────────────────────────────

    public function publish(int|Workflow $workflow, ?string $principalReference = null): WorkflowRevision
    {
        $workflow = $this->resolveWorkflow($workflow);

        return DB::transaction(function () use ($workflow, $principalReference): WorkflowRevision {
            $locked = Workflow::query()->lockForUpdate()->findOrFail($workflow->id);
            $this->validator->validate($locked);

            $definition = $this->snapshots->captureDraft($locked);
            $hash = $this->snapshots->hash($definition);
            $existing = $locked->revisions()->where('definition_hash', $hash)->first();

            if ($existing instanceof WorkflowRevision) {
                return $existing;
            }

            $version = ((int) $locked->revisions()->max('version')) + 1;

            return $locked->revisions()->create([
                'version' => $version,
                'definition' => $definition,
                'definition_hash' => $hash,
                'published_by_reference' => $principalReference,
                'published_at' => now(),
            ]);
        });
    }

    public function activate(int|Workflow $workflow, int|WorkflowRevision|null $revision = null): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);
        $revision = $revision === null
            ? $this->publish($workflow)
            : $this->resolveRevision($revision);

        DB::transaction(function () use ($workflow, $revision): void {
            $locked = Workflow::query()->lockForUpdate()->findOrFail($workflow->id);

            if ($revision->workflow_id !== $locked->id) {
                throw new LogicException("Revision {$revision->id} does not belong to workflow {$locked->id}.");
            }

            $locked->update([
                'is_active' => true,
                'active_revision_id' => $revision->id,
            ]);
            $this->schedules->activate($locked->fresh('activeRevision'));
        });

        return $workflow->refresh();
    }

    public function deactivate(int|Workflow $workflow): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);
        $workflow->update(['is_active' => false]);
        $this->schedules->deactivate($workflow);

        return $workflow->fresh();
    }

    /**
     * Replace the mutable editor draft with an immutable published revision.
     *
     * The active revision pointer is deliberately left unchanged. Restoring a
     * revision prepares a draft for review; publishing it is a separate action.
     */
    public function restoreDraft(
        int|Workflow $workflow,
        int|WorkflowRevision $revision,
    ): Workflow {
        $workflow = $this->resolveWorkflow($workflow);
        $revision = $this->resolveRevision($revision);

        if ($revision->workflow_id !== $workflow->id) {
            throw new LogicException("Revision {$revision->id} does not belong to workflow {$workflow->id}.");
        }

        $definition = $this->snapshots->fromRevision($revision);

        DB::transaction(function () use ($workflow, $definition): void {
            $locked = Workflow::query()->lockForUpdate()->findOrFail($workflow->id);
            $locked->edges()->delete();
            $locked->nodes()->delete();
            $locked->update([
                'settings' => is_array($definition['settings'] ?? null)
                    ? $definition['settings']
                    : [],
            ]);

            $nodeIdMap = [];
            foreach ($definition['nodes'] ?? [] as $node) {
                if (! is_array($node) || ! isset($node['id'], $node['key'], $node['type'])) {
                    throw new LogicException('The workflow revision contains an invalid node definition.');
                }

                $restoredNode = $locked->nodes()->create([
                    'type' => NodeType::from((string) $node['type']),
                    'node_key' => (string) $node['key'],
                    'name' => is_string($node['name'] ?? null) ? $node['name'] : null,
                    'config' => is_array($node['config'] ?? null) ? $node['config'] : [],
                    'pinned_data' => is_array($node['pinned_data'] ?? null) ? $node['pinned_data'] : null,
                    'position_x' => (int) ($node['position_x'] ?? 0),
                    'position_y' => (int) ($node['position_y'] ?? 0),
                ]);
                $nodeIdMap[(string) $node['id']] = $restoredNode->id;
            }

            foreach ($definition['edges'] ?? [] as $edge) {
                if (! is_array($edge)) {
                    throw new LogicException('The workflow revision contains an invalid edge definition.');
                }

                $sourceNodeId = $nodeIdMap[(string) ($edge['source_node_id'] ?? '')] ?? null;
                $targetNodeId = $nodeIdMap[(string) ($edge['target_node_id'] ?? '')] ?? null;

                if ($sourceNodeId === null || $targetNodeId === null) {
                    throw new LogicException('The workflow revision contains an edge with a missing node.');
                }

                $locked->edges()->create([
                    'source_node_id' => $sourceNodeId,
                    'source_port' => (string) ($edge['source_port'] ?? 'main'),
                    'target_node_id' => $targetNodeId,
                    'target_port' => (string) ($edge['target_port'] ?? 'main'),
                ]);
            }
        });

        return $workflow->fresh(['nodes', 'edges', 'activeRevision']);
    }

    /**
     * Validate a workflow and return error messages.
     *
     * @return string[]
     */
    public function validate(int|Workflow $workflow): array
    {
        $workflow = $this->resolveWorkflow($workflow);

        return $this->validator->errors($workflow);
    }

    // ── Builder helpers ────────────────────────────────────────────

    /** @param array<string, mixed> $config */
    public function addNode(
        int|Workflow $workflow,
        string $nodeKey,
        array $config = [],
        ?string $name = null,
        int $positionX = 0,
        int $positionY = 0,
    ): WorkflowNode {
        return $this->withGraphLock($this->resolveWorkflow($workflow), function (Workflow $workflow) use ($nodeKey, $config, $name, $positionX, $positionY): WorkflowNode {
            $config = $this->configValidator->validate($nodeKey, $config, $this->scopeResolver->resolve($workflow));

            return $workflow->nodes()->create([
                'type' => app(NodeRegistry::class)->getMeta($nodeKey)['type'] ?? NodeType::Action,
                'node_key' => $nodeKey,
                'name' => $name,
                'config' => $config,
                'position_x' => $positionX,
                'position_y' => $positionY,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateNode(int|WorkflowNode $node, array $data): WorkflowNode
    {
        $node = $node instanceof WorkflowNode ? $node : ConfiguredModels::node()::findOrFail($node);

        return $this->withGraphLock($node->workflow, function (Workflow $workflow) use ($node, $data): WorkflowNode {
            $node = $workflow->nodes()->findOrFail($node->id);
            if (array_key_exists('config', $data)) {
                $data['config'] = $this->configValidator->validate(
                    $node->node_key,
                    is_array($data['config']) ? $data['config'] : [],
                    $this->scopeResolver->resolve($workflow),
                );
            }
            $node->update($data);

            return $node->fresh();
        });
    }

    public function connect(
        int|WorkflowNode $source,
        int|WorkflowNode $target,
        string $sourcePort = 'main',
        string $targetPort = 'main',
    ): WorkflowEdge {
        $source = $source instanceof WorkflowNode ? $source : ConfiguredModels::node()::findOrFail($source);
        $targetId = $target instanceof WorkflowNode ? $target->id : $target;

        return $this->withGraphLock($source->workflow, function (Workflow $workflow) use ($source, $targetId, $sourcePort, $targetPort): WorkflowEdge {
            $workflow->nodes()->findOrFail([$source->id, $targetId]);

            return $workflow->edges()->create([
                'source_node_id' => $source->id,
                'source_port' => $sourcePort,
                'target_node_id' => $targetId,
                'target_port' => $targetPort,
            ]);
        });
    }

    public function removeNode(int $nodeId): void
    {
        $node = ConfiguredModels::node()::findOrFail($nodeId);
        $this->withGraphLock($node->workflow, function (Workflow $workflow) use ($nodeId): void {
            $workflow->edges()->where(fn ($query) => $query->where('source_node_id', $nodeId)->orWhere('target_node_id', $nodeId))->delete();
            $workflow->nodes()->findOrFail($nodeId)->delete();
        });
    }

    public function removeEdge(int $edgeId): void
    {
        $edge = ConfiguredModels::edge()::findOrFail($edgeId);
        $this->withGraphLock($edge->workflow, fn (Workflow $workflow) => $workflow->edges()->findOrFail($edgeId)->delete());
    }

    /**
     * All package graph writers take the workflow lock, including legacy endpoints.
     *
     * @template TResult
     *
     * @param  callable(Workflow): TResult  $callback
     * @return TResult
     */
    private function withGraphLock(Workflow $workflow, callable $callback): mixed
    {
        return $workflow->getConnection()->transaction(fn () => $callback($workflow->newQuery()->lockForUpdate()->findOrFail($workflow->id)));
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function resolveWorkflow(int|Workflow $workflow): Workflow
    {
        return $workflow instanceof Workflow ? $workflow : Workflow::findOrFail($workflow);
    }

    private function resolveRun(int|WorkflowRun $run): WorkflowRun
    {
        return $run instanceof WorkflowRun ? $run : WorkflowRun::findOrFail($run);
    }

    private function resolveRevision(int|WorkflowRevision $revision): WorkflowRevision
    {
        return $revision instanceof WorkflowRevision
            ? $revision
            : WorkflowRevision::findOrFail($revision);
    }

    private function defaultIdempotencyScope(Workflow $workflow, ExecutionScope $scope): string
    {
        return 'workflow:'.$workflow->id.':'.hash('sha256', implode("\0", [
            $scope->tenantReference ?? '',
            $scope->principalReference ?? '',
        ]));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
