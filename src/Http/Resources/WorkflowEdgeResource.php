<?php

namespace Aitumalow\Http\Resources;

use Aitumalow\Models\WorkflowEdge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowEdge */
class WorkflowEdgeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'source_node_id' => $this->source_node_id,
            'source_port' => $this->source_port,
            'target_node_id' => $this->target_node_id,
            'target_port' => $this->target_port,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
