<?php

declare(strict_types=1);

namespace Aitumalow\Http\Resources;

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowRevision */
final class WorkflowRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $workflow = $request->route('workflow');

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'workflow_id' => $this->workflow_id,
            'version' => $this->version,
            'definition_hash' => $this->definition_hash,
            'published_by_reference' => $this->published_by_reference,
            'published_at' => $this->published_at->toISOString(),
            'is_active' => $workflow instanceof Workflow
                && $workflow->active_revision_id === $this->id,
        ];
    }
}
