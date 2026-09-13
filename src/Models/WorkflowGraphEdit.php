<?php

declare(strict_types=1);

namespace Aitumalow\Models;

use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Editor receipts are independent of published revisions and Durable execution history.
 *
 * @property int $id
 * @property int $workflow_id
 * @property string $request_id
 * @property string $request_hash
 * @property string $operation
 * @property array<string, mixed> $before
 * @property array<string, mixed> $after
 * @property string $before_hash
 * @property string $after_hash
 * @property int|null $created_node_id
 */
class WorkflowGraphEdit extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('aitumalow.tables.graph_edits', 'aitumalow_workflow_graph_edits');
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::workflow());
    }

    /** @return array{id: int, operation: string, before_hash: string, after_hash: string, created_node_id: int|null} */
    public function receipt(): array
    {
        return $this->only(['id', 'operation', 'before_hash', 'after_hash', 'created_node_id']);
    }
}
