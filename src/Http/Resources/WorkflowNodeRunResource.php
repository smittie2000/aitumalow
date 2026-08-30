<?php

namespace Aitumalow\Http\Resources;

use Aitumalow\Models\WorkflowNodeRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowNodeRun */
class WorkflowNodeRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_run_id' => $this->workflow_run_id,
            'node_id' => $this->node_id,
            'status' => $this->status->value,
            'input' => $this->input,
            'output' => $this->output,
            'error_message' => $this->error_message,
            'duration_ms' => $this->duration_ms,
            'attempts' => $this->attempts,
            'executed_at' => $this->executed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
