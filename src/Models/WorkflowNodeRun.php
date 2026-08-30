<?php

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowNodeRunFactory;
use Aitumalow\Enums\NodeRunStatus;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_run_id
 * @property int $node_id
 * @property string|null $durable_activity_id
 * @property NodeRunStatus $status
 * @property array<int, array<string, mixed>>|null $input
 * @property array<string, array<int, array<string, mixed>>>|null $output
 * @property string|null $error_message
 * @property int|null $duration_ms
 * @property int $attempts
 * @property Carbon|null $executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WorkflowRun $workflowRun
 * @property-read WorkflowNode|null $node
 */
class WorkflowNodeRun extends Model
{
    /** @use HasFactory<WorkflowNodeRunFactory> */
    use HasFactory;

    protected static function newFactory(): WorkflowNodeRunFactory
    {
        return WorkflowNodeRunFactory::new();
    }

    protected $guarded = [];

    public function getTable(): string
    {
        return config('aitumalow.tables.node_runs', 'aitumalow_workflow_node_runs');
    }

    protected function casts(): array
    {
        return [
            'status' => NodeRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkflowRun, $this> */
    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::run(),
            'workflow_run_id',
        );
    }

    /** @return BelongsTo<WorkflowNode, $this> */
    public function node(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::node(),
            'node_id',
        );
    }
}
