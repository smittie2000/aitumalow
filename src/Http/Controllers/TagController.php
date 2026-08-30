<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Resources\WorkflowTagResource;
use Aitumalow\Models\WorkflowTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class TagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tags = WorkflowTag::query()
            ->withCount('workflows')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->get();

        return WorkflowTagResource::collection($tags);
    }

    public function store(Request $request): WorkflowTagResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:'.config('aitumalow.tables.tags', 'aitumalow_workflow_tags').',name'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $tag = WorkflowTag::create($data);

        return new WorkflowTagResource($tag);
    }

    public function update(Request $request, WorkflowTag $tag): WorkflowTagResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:'.config('aitumalow.tables.tags', 'aitumalow_workflow_tags').',name,'.$tag->id],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $tag->update($data);

        return new WorkflowTagResource($tag);
    }

    public function destroy(WorkflowTag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(['message' => 'Tag deleted.']);
    }
}
