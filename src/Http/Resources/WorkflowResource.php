<?php

namespace Aitumalow\Http\Resources;

use Aitumalow\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workflow */
class WorkflowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'active_revision_id' => $this->active_revision_id,
            'active_revision' => $this->whenLoaded('activeRevision', fn (): ?array => $this->activeRevision === null ? null : [
                'id' => $this->activeRevision->id,
                'version' => $this->activeRevision->version,
                'definition_hash' => $this->activeRevision->definition_hash,
                'published_by_reference' => $this->activeRevision->published_by_reference,
                'published_at' => $this->activeRevision->published_at->toISOString(),
            ]),
            'settings' => $this->settings,
            'created_via' => $this->created_via?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'folder_id' => $this->folder_id,
            'folder' => new WorkflowFolderResource($this->whenLoaded('folder')),
            'tags' => WorkflowTagResource::collection($this->whenLoaded('tags')),
            'nodes' => WorkflowNodeResource::collection($this->whenLoaded('nodes')),
            'edges' => WorkflowEdgeResource::collection($this->whenLoaded('edges')),
        ];
    }
}
