<?php

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowFolderFactory;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WorkflowFolder|null $parent
 * @property-read Collection<int, WorkflowFolder> $children
 * @property-read Collection<int, Workflow> $workflows
 */
class WorkflowFolder extends Model
{
    /** @use HasFactory<WorkflowFolderFactory> */
    use HasFactory;

    protected static function newFactory(): WorkflowFolderFactory
    {
        return WorkflowFolderFactory::new();
    }

    protected $guarded = [];

    public function getTable(): string
    {
        return config('aitumalow.tables.folders', 'aitumalow_workflow_folders');
    }

    /** @return BelongsTo<WorkflowFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::folder(),
            'parent_id',
        );
    }

    /** @return HasMany<WorkflowFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::folder(),
            'parent_id',
        );
    }

    /** @return HasMany<Workflow, $this> */
    public function workflows(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::workflow(),
            'folder_id',
        );
    }

    /** @return list<WorkflowFolder> */
    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return array_reverse($ancestors);
    }
}
