<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Resources\WorkflowFolderResource;
use Aitumalow\Models\WorkflowFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class FolderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $folders = WorkflowFolder::query()
            ->withCount('workflows')
            ->when(
                $request->boolean('tree'),
                fn ($q) => $q->whereNull('parent_id')->with(['children' => fn ($q) => $q->withCount('workflows')]),
                fn ($q) => $q->orderBy('name'),
            )
            ->get();

        return WorkflowFolderResource::collection($folders);
    }

    public function store(Request $request): WorkflowFolderResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:'.config('aitumalow.tables.folders', 'aitumalow_workflow_folders').',id'],
        ]);

        $folder = WorkflowFolder::create($data);

        return new WorkflowFolderResource($folder);
    }

    public function update(Request $request, WorkflowFolder $folder): WorkflowFolderResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:'.config('aitumalow.tables.folders', 'aitumalow_workflow_folders').',id'],
        ]);

        if (isset($data['parent_id']) && $data['parent_id'] === $folder->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A folder cannot be its own parent.',
            ]);
        }

        $folder->update($data);

        return new WorkflowFolderResource($folder);
    }

    public function destroy(WorkflowFolder $folder): JsonResponse
    {
        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }
}
