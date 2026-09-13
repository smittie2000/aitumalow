<?php

namespace Aitumalow\Http\Resources;

use Aitumalow\Models\WorkflowNode;
use Aitumalow\Registry\NodeRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowNode */
class WorkflowNodeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'type' => $this->type->value,
            'node_key' => $this->node_key,
            'name' => $this->name,
            'config' => $this->config,
            'pinned_data' => $this->pinned_data,
            ...app(NodeRegistry::class)->ports($this->node_key, $this->config ?? []),
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
