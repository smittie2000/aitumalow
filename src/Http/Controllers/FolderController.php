<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Resources\WorkflowFolderResource;
use Aitumalow\Models\WorkflowFolder;
use Illuminate\Database\Eloquent\Collection;
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
            ->orderBy('name')
            ->get();

        if ($request->boolean('tree')) {
            $folders = $this->buildTree($folders);
        }

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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:'.config('aitumalow.tables.folders', 'aitumalow_workflow_folders').',id'],
        ]);

        if (isset($data['parent_id'])) {
            $this->assertParentDoesNotCreateCycle($folder, $data['parent_id']);
        }

        $folder->update($data);

        return new WorkflowFolderResource($folder);
    }

    public function destroy(WorkflowFolder $folder): JsonResponse
    {
        if ($folder->children()->exists() || $folder->workflows()->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'Move or remove this folder\'s workflows and subfolders before deleting it.',
            ]);
        }

        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }

    /**
     * @param  Collection<int, WorkflowFolder>  $folders
     * @return Collection<int, WorkflowFolder>
     */
    private function buildTree(Collection $folders, ?int $parentId = null): Collection
    {
        return $folders
            ->filter(fn (WorkflowFolder $folder): bool => $folder->parent_id === $parentId)
            ->values()
            ->each(function (WorkflowFolder $folder) use ($folders): void {
                $folder->setRelation('children', $this->buildTree($folders, $folder->id));
            });
    }

    private function assertParentDoesNotCreateCycle(WorkflowFolder $folder, int $parentId): void
    {
        $current = WorkflowFolder::query()->findOrFail($parentId);
        $visited = [];

        while ($current) {
            if ($current->is($folder) || isset($visited[$current->id])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A folder cannot be moved inside itself or one of its descendants.',
                ]);
            }

            $visited[$current->id] = true;
            $current = $current->parent;
        }
    }
}
