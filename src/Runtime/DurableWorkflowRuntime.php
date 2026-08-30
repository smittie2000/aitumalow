<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\RunCommand;
use Aitumalow\Enums\CommandStatus;
use Aitumalow\Enums\NodeRunStatus;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowCommand;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Models\WorkflowRevision;
use Aitumalow\Models\WorkflowRun;
use LogicException;
use Throwable;
use Workflow\V2\StartOptions;
use Workflow\V2\WorkflowStub;

final readonly class DurableWorkflowRuntime
{
    public function __construct(
        private WorkflowSnapshot $snapshots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $subjectContext
     */
    public function start(
        Workflow $workflow,
        WorkflowRevision $revision,
        array $payload,
        ExecutionScope $scope,
        ?int $stopAfterNodeId = null,
        ?string $subjectType = null,
        ?string $subjectReference = null,
        array $subjectContext = [],
        ?string $subjectFreshness = null,
        ?string $executorReference = null,
        ?string $idempotencyScope = null,
        ?string $idempotencyKey = null,
    ): WorkflowRun {
        if ($revision->workflow_id !== $workflow->id) {
            throw new LogicException("Revision {$revision->id} does not belong to workflow {$workflow->id}.");
        }

        $attributes = [
            'workflow_id' => $workflow->id,
            'workflow_revision_id' => $revision->id,
            'status' => RunStatus::Pending,
            'trigger_node_id' => data_get($revision->definition, 'trigger_node_id'),
            'execution_scope' => $scope->toArray(),
            'initial_payload' => $payload,
            'subject_type' => $subjectType,
            'subject_reference' => $subjectReference,
            'subject_context' => $subjectContext !== [] ? $subjectContext : null,
            'subject_freshness' => $subjectFreshness,
            'executor_reference' => $executorReference,
            'idempotency_scope' => $idempotencyScope,
            'idempotency_key' => $idempotencyKey,
        ];

        $projection = $idempotencyKey === null
            ? WorkflowRun::query()->create($attributes)
            : WorkflowRun::query()->firstOrCreate([
                'idempotency_scope' => $idempotencyScope,
                'idempotency_key' => $idempotencyKey,
            ], $attributes);

        if ($projection->workflow_id !== $workflow->id) {
            throw new LogicException('The idempotency scope and key already identify a different workflow.');
        }

        if ($projection->durable_workflow_id !== null && $projection->durable_run_id !== null) {
            return $projection->fresh();
        }

        $workflow = $projection->workflow;
        $revision = $projection->revision;
        $payload = $projection->initial_payload ?? [];
        $scope = ExecutionScope::fromArray($projection->execution_scope);
        $snapshot = $this->snapshots->fromRevision($revision);
        $snapshot['test_mode'] = $stopAfterNodeId !== null;
        $runMetadata = [
            'subject_type' => $projection->subject_type,
            'subject_reference' => $projection->subject_reference,
            'subject_context' => $projection->subject_context ?? [],
            'subject_freshness' => $projection->subject_freshness,
            'executor_reference' => $projection->executor_reference,
            'idempotency_scope' => $projection->idempotency_scope,
            'idempotency_key' => $projection->idempotency_key,
        ];

        $projection->update([
            'status' => RunStatus::Pending,
            'error_message' => null,
            'finished_at' => null,
        ]);

        try {
            $stub = WorkflowStub::make(DynamicGraphWorkflow::class, 'aitumalow.run.'.$projection->id);
            $result = $stub->start(
                $snapshot,
                $payload,
                $scope->toArray(),
                $projection->id,
                $stopAfterNodeId,
                $runMetadata,
                StartOptions::returnExistingActive()->withBusinessKey(
                    $projection->idempotency_key ?? 'aitumalow.run.'.$projection->id,
                )->withLabels(
                    [
                        'aitumalow_workflow_id' => (string) $workflow->id,
                        'aitumalow_revision_id' => (string) $revision->id,
                    ],
                ),
            );

            $durableWorkflowId = $result->workflowId();
            $durableRunId = $result->runId();
            if ($durableWorkflowId === null || $durableRunId === null) {
                throw new LogicException('Durable Workflow did not return execution identifiers.');
            }

            $projection->update([
                'durable_workflow_id' => $durableWorkflowId,
                'durable_run_id' => $durableRunId,
                'status' => RunStatus::Running,
                'started_at' => now(),
            ]);

            $this->refreshProjection($projection);
        } catch (Throwable $exception) {
            $projection->update([
                'status' => RunStatus::Failed,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }

        return $projection->fresh();
    }

    public function command(
        WorkflowRun $run,
        RunCommand $request,
        ExecutionScope $scope,
        int $expectedNodeId,
        bool $waitForCompletion = false,
        ?int $effectNodeId = null,
    ): WorkflowCommand {
        $command = $run->commands()->where('idempotency_key', $request->idempotencyKey)->first();

        if ($command instanceof WorkflowCommand) {
            if ($command->name !== $request->name
                || $command->expected_node_id !== $expectedNodeId
                || $command->payload !== $request->payload) {
                throw new LogicException('The workflow command idempotency key was reused with a different command.');
            }

            return $this->synchronizeCommand($command);
        }

        if ($run->isFinished()) {
            throw new LogicException("Aitumalow run {$run->id} is already finished.");
        }

        $command = $run->commands()->firstOrCreate(
            ['idempotency_key' => $request->idempotencyKey],
            [
                'name' => $request->name,
                'expected_node_id' => $expectedNodeId,
                'payload' => $request->payload,
                'execution_scope' => $scope->toArray(),
                'status' => CommandStatus::Pending,
            ],
        );
        $created = $command->wasRecentlyCreated;

        if ($command->name !== $request->name
            || $command->expected_node_id !== $expectedNodeId
            || $command->payload !== $request->payload) {
            throw new LogicException('The workflow command idempotency key was reused with a different command.');
        }

        if ($command->durable_update_id !== null) {
            return $this->synchronizeCommand($command);
        }

        $previousEffectRunId = $effectNodeId === null
            ? null
            : $run->nodeRuns()->where('node_id', $effectNodeId)->max('id');

        try {
            $result = $this->stub($run)->submitUpdate(
                'command',
                $request->name,
                $expectedNodeId,
                $request->payload,
                $scope->toArray(),
            );

            $attributes = [
                'durable_update_id' => $result->updateId(),
                'status' => $result->rejected()
                    ? CommandStatus::Rejected
                    : CommandStatus::Accepted,
                'error_message' => $result->rejected() ? ($result->message() ?? $result->rejectionReason()) : null,
            ];

            if ($result->completed()) {
                $attributes['status'] = CommandStatus::Completed;
                $attributes['result'] = is_array($result->result())
                    ? $result->result()
                    : ['value' => $result->result()];
                $attributes['applied_at'] = $result->appliedAt() ?? now();
            } elseif ($result->failed()) {
                $attributes['status'] = CommandStatus::Failed;
                $attributes['error_message'] = $result->failureMessage();
            }

            $command->update($attributes);

            if ($waitForCompletion && $created && $effectNodeId !== null && ! $result->rejected() && ! $result->failed()) {
                $effect = $this->waitForNodeEffect($run, $effectNodeId, is_int($previousEffectRunId) ? $previousEffectRunId : 0);

                if (! $effect instanceof WorkflowNodeRun) {
                    $command->update([
                        'error_message' => 'The workflow command was accepted, but its business effect did not finish before the wrapper timeout.',
                    ]);
                } elseif ($effect->status === NodeRunStatus::Failed) {
                    $command->update([
                        'status' => CommandStatus::Failed,
                        'error_message' => $effect->error_message,
                    ]);
                } else {
                    $command = $this->synchronizeCommand($command);
                }
            }
        } catch (Throwable $exception) {
            $command->update([
                'status' => CommandStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $command->fresh();
    }

    private function waitForNodeEffect(WorkflowRun $run, int $nodeId, int $afterId): ?WorkflowNodeRun
    {
        $timeout = max(1, (int) config('aitumalow.command_effect_timeout_seconds', 15));
        $deadline = microtime(true) + $timeout;

        do {
            $effect = $run->nodeRuns()
                ->where('node_id', $nodeId)
                ->where('id', '>', $afterId)
                ->whereIn('status', [NodeRunStatus::Completed->value, NodeRunStatus::Failed->value])
                ->oldest('id')
                ->first();

            if ($effect instanceof WorkflowNodeRun) {
                return $effect;
            }

            if (WorkflowStub::faked()) {
                if (WorkflowStub::runReadyTasks() === 0) {
                    return null;
                }
            } else {
                usleep(50_000);
            }
        } while (microtime(true) < $deadline);

        return null;
    }

    /**
     * Read the replayed workflow state without relying on the local projection.
     *
     * @return array{
     *     waiting_node_id: int|null,
     *     waiting_state: string|null,
     *     accepted_commands: list<string>
     * }
     */
    public function currentState(WorkflowRun $run): array
    {
        $state = $this->stub($run)->query('aitumalow.current-state');

        if (! is_array($state)) {
            throw new LogicException("Aitumalow run {$run->id} returned an invalid Durable query state.");
        }

        $acceptedCommands = array_values(array_filter(
            is_array($state['accepted_commands'] ?? null) ? $state['accepted_commands'] : [],
            is_string(...),
        ));

        return [
            'waiting_node_id' => is_int($state['waiting_node_id'] ?? null)
                ? $state['waiting_node_id']
                : null,
            'waiting_state' => is_string($state['waiting_state'] ?? null)
                ? $state['waiting_state']
                : null,
            'accepted_commands' => $acceptedCommands,
        ];
    }

    public function synchronizeCommand(WorkflowCommand $command): WorkflowCommand
    {
        if ($command->durable_update_id === null) {
            return $command;
        }

        $result = $this->stub($command->run)->inspectUpdate($command->durable_update_id);
        $attributes = [];

        if ($result->completed()) {
            $attributes = [
                'status' => CommandStatus::Completed,
                'result' => is_array($result->result()) ? $result->result() : ['value' => $result->result()],
                'error_message' => null,
                'applied_at' => $result->appliedAt() ?? now(),
            ];
        } elseif ($result->failed()) {
            $attributes = [
                'status' => CommandStatus::Failed,
                'error_message' => $result->failureMessage(),
            ];
        } elseif ($result->rejected()) {
            $attributes = [
                'status' => CommandStatus::Rejected,
                'error_message' => $result->message() ?? $result->rejectionReason(),
            ];
        }

        if ($attributes !== []) {
            $command->update($attributes);
        }

        return $command->fresh();
    }

    public function cancel(WorkflowRun $run): WorkflowRun
    {
        if ($run->isFinished()) {
            return $run;
        }

        $this->stub($run)->cancel('Cancelled through Aitumalow.');

        $run->update([
            'status' => RunStatus::Cancelled,
            'finished_at' => now(),
        ]);

        return $run->fresh();
    }

    public function synchronizeRun(WorkflowRun $run): WorkflowRun
    {
        if ($run->durable_workflow_id === null || $run->durable_run_id === null) {
            return $run;
        }

        $stub = $this->stub($run);
        $status = match ($stub->status()) {
            'pending' => RunStatus::Pending,
            'running' => RunStatus::Running,
            'waiting' => RunStatus::Waiting,
            'completed' => RunStatus::Completed,
            'cancelled' => RunStatus::Cancelled,
            'failed', 'terminated' => RunStatus::Failed,
            default => $run->status,
        };

        $attributes = ['status' => $status];
        if ($status === RunStatus::Completed) {
            $attributes['context'] = is_array($stub->output()) ? $stub->output() : [];
            $attributes['waiting_node_id'] = null;
        }
        if (in_array($status, [RunStatus::Completed, RunStatus::Cancelled, RunStatus::Failed], true)) {
            $attributes['finished_at'] = $run->finished_at ?? now();
        }

        $run->update($attributes);

        return $run->refresh();
    }

    private function stub(WorkflowRun $run): WorkflowStub
    {
        if ($run->durable_workflow_id === null || $run->durable_run_id === null) {
            throw new LogicException("Aitumalow run {$run->id} has no Durable Workflow execution.");
        }

        return WorkflowStub::loadSelection($run->durable_workflow_id, $run->durable_run_id);
    }

    private function refreshProjection(WorkflowRun $run): void
    {
        $stub = $this->stub($run);

        if ($stub->completed()) {
            $run->update([
                'status' => RunStatus::Completed,
                'context' => is_array($stub->output()) ? $stub->output() : [],
                'finished_at' => now(),
            ]);
        } elseif ($stub->failed()) {
            $run->update([
                'status' => RunStatus::Failed,
                'finished_at' => now(),
            ]);
        }
    }
}
