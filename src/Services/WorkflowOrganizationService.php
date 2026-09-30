<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\Models\WorkflowFolder;
use Aitumalow\Models\WorkflowTag;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class WorkflowOrganizationService
{
    /** @return Collection<int, WorkflowFolder> */
    public function folders(bool $tree = false): Collection
    {
        $folders = ConfiguredModels::folder()::query()->withCount('workflows')->orderBy('name')->get();

        return $tree ? $this->buildTree($folders) : $folders;
    }

    /** @return Collection<int, WorkflowTag> */
    public function tags(string $search = ''): Collection
    {
        return ConfiguredModels::tag()::query()->withCount('workflows')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function saveFolder(array $data, ?WorkflowFolder $folder = null): WorkflowFolder
    {
        $data = Validator::make($data, [
            'name' => $folder ? ['sometimes', 'required', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:'.config('aitumalow.tables.folders', 'aitumalow_workflow_folders').',id'],
        ])->validate();
        if (isset($data['parent_id'])) {
            $current = ConfiguredModels::folder()::query()->findOrFail((int) $data['parent_id']);
            $visited = [];
            while ($current) {
                if (($folder && $current->is($folder)) || isset($visited[$current->id])) {
                    throw ValidationException::withMessages(['parent_id' => 'A folder cannot be moved inside itself or one of its descendants.']);
                }
                $visited[$current->id] = true;
                $current = $current->parent;
            }
        }
        if ($folder) {
            $folder->update($data);

            return $folder;
        }

        return ConfiguredModels::folder()::create($data);
    }

    public function deleteFolder(WorkflowFolder $folder): void
    {
        if ($folder->children()->exists() || $folder->workflows()->exists()) {
            throw ValidationException::withMessages(['folder' => "Move or remove this folder's workflows and subfolders before deleting it."]);
        }
        $folder->delete();
    }

    /** @param array<string, mixed> $data */
    public function saveTag(array $data, ?WorkflowTag $tag = null): WorkflowTag
    {
        $data = Validator::make($data, [
            'name' => [$tag ? 'sometimes' : 'required', 'string', 'max:255', 'unique:'.config('aitumalow.tables.tags', 'aitumalow_workflow_tags').',name'.($tag ? ','.$tag->id : '')],
            'color' => ['nullable', 'string', 'max:7'],
        ])->validate();
        if ($tag) {
            $tag->update($data);

            return $tag;
        }

        return ConfiguredModels::tag()::create($data);
    }

    public function deleteTag(WorkflowTag $tag): void
    {
        $tag->delete();
    }

    /** @param Collection<int, WorkflowFolder> $folders
     * @return Collection<int, WorkflowFolder>
     */
    private function buildTree(Collection $folders, ?int $parentId = null): Collection
    {
        return $folders->filter(fn (WorkflowFolder $folder): bool => $folder->parent_id === $parentId)->values()
            ->each(fn (WorkflowFolder $folder) => $folder->setRelation('children', $this->buildTree($folders, $folder->id)));
    }
}
