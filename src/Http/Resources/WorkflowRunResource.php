<?php

namespace Aitumalow\Http\Resources;

use Aitumalow\Models\WorkflowRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowRun */
class WorkflowRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->synchronizeDurableState();

        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'workflow_revision_id' => $this->workflow_revision_id,
            'status' => $this->status->value,
            'trigger_node_id' => $this->trigger_node_id,
            'waiting_node_id' => $this->waiting_node_id,
            'waiting_state' => $this->waiting_state,
            'subject_type' => $this->subject_type,
            'subject_reference' => $this->subject_reference,
            'subject_context' => $this->subject_context,
            'subject_freshness' => $this->subject_freshness,
            'executor_reference' => $this->executor_reference,
            'initial_payload' => $this->initial_payload,
            'error_message' => $this->error_message,
            'duration_ms' => $this->started_at && $this->finished_at
                ? (int) $this->started_at->diffInMilliseconds($this->finished_at)
                : null,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'node_runs' => WorkflowNodeRunResource::collection($this->whenLoaded('nodeRuns')),
            'commands' => WorkflowCommandResource::collection($this->whenLoaded('commands')),
        ];
    }
}
