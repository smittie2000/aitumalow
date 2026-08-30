<?php

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowEdgeFactory;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_id
 * @property int $source_node_id
 * @property string $source_port
 * @property int $target_node_id
 * @property string $target_port
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 * @property-read WorkflowNode $sourceNode
 * @property-read WorkflowNode $targetNode
 */
class WorkflowEdge extends Model
{
    /** @use HasFactory<WorkflowEdgeFactory> */
    use HasFactory;

    protected static function newFactory(): WorkflowEdgeFactory
    {
        return WorkflowEdgeFactory::new();
    }

    protected $guarded = [];

    public function getTable(): string
    {
        return config('aitumalow.tables.edges', 'aitumalow_workflow_edges');
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::workflow(),
        );
    }

    /** @return BelongsTo<WorkflowNode, $this> */
    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::node(),
            'source_node_id',
        );
    }

    /** @return BelongsTo<WorkflowNode, $this> */
    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::node(),
            'target_node_id',
        );
    }
}
