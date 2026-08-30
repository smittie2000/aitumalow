<?php

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowRunFactory;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Runtime\DurableWorkflowRuntime;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $workflow_id
 * @property int $workflow_revision_id
 * @property RunStatus $status
 * @property int|null $trigger_node_id
 * @property int|null $waiting_node_id
 * @property string|null $waiting_state
 * @property string|null $durable_workflow_id
 * @property string|null $durable_run_id
 * @property array{tenant_reference: string|null, principal_reference: string|null} $execution_scope
 * @property string|null $subject_type
 * @property string|null $subject_reference
 * @property array<string, mixed>|null $subject_context
 * @property string|null $subject_freshness
 * @property string|null $executor_reference
 * @property string|null $idempotency_scope
 * @property string|null $idempotency_key
 * @property array<int, array<string, mixed>>|null $initial_payload
 * @property array<int, array<string, array<int, array<string, mixed>>>>|null $context
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 * @property-read WorkflowRevision $revision
 * @property-read WorkflowNode|null $triggerNode
 * @property-read Collection<int, WorkflowNodeRun> $nodeRuns
 * @property-read Collection<int, WorkflowCommand> $commands
 */
class WorkflowRun extends Model
{
    /** @use HasFactory<WorkflowRunFactory> */
    use HasFactory;

    use HasUlids;

    protected static function newFactory(): WorkflowRunFactory
    {
        return WorkflowRunFactory::new();
    }

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getTable(): string
    {
        return config('aitumalow.tables.runs', 'aitumalow_workflow_runs');
    }

    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'execution_scope' => 'array',
            'subject_context' => 'array',
            'initial_payload' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::workflow(),
        );
    }

    /** @return BelongsTo<WorkflowRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::revision(),
            'workflow_revision_id',
        );
    }

    /** @return BelongsTo<WorkflowNode, $this> */
    public function triggerNode(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::node(),
            'trigger_node_id',
        );
    }

    /** @return HasMany<WorkflowNodeRun, $this> */
    public function nodeRuns(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::nodeRun(),
            'workflow_run_id',
        );
    }

    /** @return HasMany<WorkflowCommand, $this> */
    public function commands(): HasMany
    {
        return $this->hasMany(ConfiguredModels::command(), 'workflow_run_id');
    }

    public function isRunning(): bool
    {
        return $this->status === RunStatus::Running;
    }

    public function isWaiting(): bool
    {
        return $this->status === RunStatus::Waiting;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            RunStatus::Completed,
            RunStatus::Failed,
            RunStatus::Cancelled,
        ]);
    }

    public function synchronizeDurableState(): static
    {
        app(DurableWorkflowRuntime::class)->synchronizeRun($this);

        return $this->refresh();
    }
}
