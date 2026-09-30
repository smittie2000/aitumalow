<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\Models\Workflow;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Builder;

final class WorkflowListingService
{
    /** @param array<string, mixed> $filters
     * @return Builder<Workflow>
     */
    public function query(array $filters): Builder
    {
        $sort = in_array($filters['sort'] ?? null, ['name', 'created_at', 'updated_at'], true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';
        $query = ConfiguredModels::workflow()::query()->with(['tags', 'folder', 'activeRevision'])->withCount(['nodes', 'edges']);
        if (isset($filters['search']) && $filters['search'] !== '') {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['active_only'])) {
            $query->where('is_active', true);
        }
        if (isset($filters['folder_id']) && $filters['folder_id'] !== '') {
            $query->where('folder_id', $filters['folder_id']);
        }
        if (! empty($filters['uncategorized'])) {
            $query->whereNull('folder_id');
        }
        if (! empty($filters['tag'])) {
            $tags = is_array($filters['tag']) ? $filters['tag'] : [$filters['tag']];
            $query->whereHas('tags', fn ($q) => $q->whereIn('name', $tags));
        }
        if (! empty($filters['tag_id'])) {
            $ids = is_array($filters['tag_id']) ? $filters['tag_id'] : [$filters['tag_id']];
            $query->whereHas('tags', fn ($q) => $q->whereIn($q->getModel()->getTable().'.id', $ids));
        }

        return $query->orderBy($sort, $direction);
    }
}
