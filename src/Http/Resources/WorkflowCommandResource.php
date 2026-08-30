<?php

declare(strict_types=1);

namespace Aitumalow\Http\Resources;

use Aitumalow\Models\WorkflowCommand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowCommand */
final class WorkflowCommandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'expected_node_id' => $this->expected_node_id,
            'status' => $this->status->value,
            'payload' => $this->payload,
            'result' => $this->result,
            'error_message' => $this->error_message,
            'applied_at' => $this->applied_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
