<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Resources\WorkflowTagResource;
use Aitumalow\Models\WorkflowTag;
use Aitumalow\Services\WorkflowOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class TagController extends Controller
{
    public function __construct(private readonly WorkflowOrganizationService $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return WorkflowTagResource::collection($this->organization->tags($request->string('search')->toString()));
    }

    public function store(Request $request): WorkflowTagResource
    {
        return new WorkflowTagResource($this->organization->saveTag($request->only(['name', 'color'])));
    }

    public function update(Request $request, WorkflowTag $tag): WorkflowTagResource
    {
        return new WorkflowTagResource($this->organization->saveTag($request->only(['name', 'color']), $tag));
    }

    public function destroy(WorkflowTag $tag): JsonResponse
    {
        $this->organization->deleteTag($tag);

        return response()->json(['message' => 'Tag deleted.']);
    }
}
