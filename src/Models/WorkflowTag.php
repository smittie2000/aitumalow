<?php

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowTagFactory;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Workflow> $workflows
 */
class WorkflowTag extends Model
{
    /** @use HasFactory<WorkflowTagFactory> */
    use HasFactory;

    protected static function newFactory(): WorkflowTagFactory
    {
        return WorkflowTagFactory::new();
    }

    protected $guarded = [];

    public function getTable(): string
    {
        return config('aitumalow.tables.tags', 'aitumalow_workflow_tags');
    }

    /** @return BelongsToMany<Workflow, $this> */
    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(
            ConfiguredModels::workflow(),
            config('aitumalow.tables.tag_pivot', 'aitumalow_workflow_tag_pivot'),
            'tag_id',
            'workflow_id',
        );
    }
}
