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

        if ($command->subject !== null) {
            $adapter = $this->subjects->get($command->subject->type);
            $subject = $adapter->resolve($command->subject->reference);

            if (! $adapter->canStart($scope, $subject)) {
                throw new AuthorizationException('The workflow subject cannot be started in this execution scope.');
            }

            $configuredSubject = data_get($revision->definition, 'settings.subject_type');
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
        $run->synchronizeDurableState();
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

        $result->run->synchronizeDurableState();

        return $result;
    }

    private function dispatchCommand(int|WorkflowRun $run, RunCommand $command, bool $waitForCompletion): WorkflowCommand
    {
        $run = $this->resolveRun($run);
        $run->synchronizeDurableState();
        if ($run->status !== RunStatus::Waiting || $run->waiting_node_id === null) {
            throw new LogicException("Aitumalow run {$run->id} is not waiting for a command.");
        }
        if ($command->expectedState !== null && $run->waiting_state !== $command->expectedState) {
            throw new LogicException(
                "Aitumalow run {$run->id} is waiting in state [{$run->waiting_state}], not [{$command->expectedState}].",
            );
        }
        $expectedNodeId = $run->waiting_node_id;

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
        $acceptedCommands = [];
        $configuredCommands = data_get($waitNode, 'config.commands');
        if (is_array($configuredCommands)) {
            foreach ($configuredCommands as $item) {
                if (is_array($item) && is_string($item['key'] ?? null)) {
                    $acceptedCommands[] = $item['key'];
                }
            }
        }
        $acceptedCommands = $acceptedCommands === [] ? ['resume'] : $acceptedCommands;
        if (! in_array($command->name, $acceptedCommands, true)) {
            throw new LogicException("Command [{$command->name}] is not accepted in state [{$run->waiting_state}].");
        }
        $scope = $command->scope ?? ExecutionScope::fromArray($run->execution_scope);
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

        if ($run->subject_type !== null && $run->subject_reference !== null) {
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

        return $this->runtime->command(
            $run,
            $command,
            $scope,
            $expectedNodeId,
            $waitForCompletion,
            $effectNodeId,
        );
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
    public function testNode(int|Workflow $workflow, int $nodeId, array $payload = []): WorkflowRun
    {
        $workflow = $this->resolveWorkflow($workflow);

        $this->validator->validate($workflow);

        return $this->runtime->start(
            workflow: $workflow,
            revision: $this->publish($workflow),
            payload: $payload,
            scope: $this->scopeResolver->resolve($workflow),
            stopAfterNodeId: $nodeId,
        );
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
    ): WorkflowNode {
        $workflow = $this->resolveWorkflow($workflow);
        $config = $this->configValidator->validate(
            $nodeKey,
            $config,
            $this->scopeResolver->resolve($workflow),
        );

        return $workflow->nodes()->create([
            'type' => app(NodeRegistry::class)->getMeta($nodeKey)['type'] ?? NodeType::Action,
            'node_key' => $nodeKey,
            'name' => $name,
            'config' => $config,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateNode(int|WorkflowNode $node, array $data): WorkflowNode
    {
        $node = $node instanceof WorkflowNode ? $node : WorkflowNode::findOrFail($node);

        if (array_key_exists('config', $data)) {
            $data['config'] = $this->configValidator->validate(
                $node->node_key,
                is_array($data['config']) ? $data['config'] : [],
                $this->scopeResolver->resolve($node->workflow),
            );
        }

        $node->update($data);

        return $node->fresh();
    }

    public function connect(
        int|WorkflowNode $source,
        int|WorkflowNode $target,
        string $sourcePort = 'main',
        string $targetPort = 'main',
    ): WorkflowEdge {
        $sourceNodeId = $source instanceof WorkflowNode ? $source->id : $source;
        $targetNodeId = $target instanceof WorkflowNode ? $target->id : $target;
        $sourceNode = $source instanceof WorkflowNode ? $source : WorkflowNode::findOrFail($sourceNodeId);

        return WorkflowEdge::create([
            'workflow_id' => $sourceNode->workflow_id,
            'source_node_id' => $sourceNodeId,
            'source_port' => $sourcePort,
            'target_node_id' => $targetNodeId,
            'target_port' => $targetPort,
        ]);
    }

    public function removeNode(int $nodeId): void
    {
        WorkflowEdge::where('source_node_id', $nodeId)
            ->orWhere('target_node_id', $nodeId)
            ->delete();

        WorkflowNode::findOrFail($nodeId)->delete();
    }

    public function removeEdge(int $edgeId): void
    {
        WorkflowEdge::findOrFail($edgeId)->delete();
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
