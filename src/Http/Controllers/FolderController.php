<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Resources\WorkflowFolderResource;
use Aitumalow\Models\WorkflowFolder;
use Aitumalow\Services\WorkflowOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class FolderController extends Controller
{
    public function __construct(private readonly WorkflowOrganizationService $organization) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return WorkflowFolderResource::collection($this->organization->folders($request->boolean('tree')));
    }

    public function store(Request $request): WorkflowFolderResource
    {
        return new WorkflowFolderResource($this->organization->saveFolder($request->only(['name', 'parent_id'])));
    }

    public function update(Request $request, WorkflowFolder $folder): WorkflowFolderResource
    {
        return new WorkflowFolderResource($this->organization->saveFolder($request->only(['name', 'parent_id']), $folder));
    }

    public function destroy(WorkflowFolder $folder): JsonResponse
    {
        $this->organization->deleteFolder($folder);

        return response()->json(['message' => 'Folder deleted.']);
    }
}
