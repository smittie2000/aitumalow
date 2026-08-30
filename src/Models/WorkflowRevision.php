<?php

declare(strict_types=1);

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowRevisionFactory;
use Aitumalow\Services\WorkflowStateGraph;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $workflow_id
 * @property int $version
 * @property array<string, mixed> $definition
 * @property string $definition_hash
 * @property string|null $published_by_reference
 * @property Carbon $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 */
class WorkflowRevision extends Model
{
    /** @use HasFactory<WorkflowRevisionFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function stateGraph(): WorkflowStateGraph
    {
        return new WorkflowStateGraph($this);
    }

    protected static function newFactory(): WorkflowRevisionFactory
    {
        return WorkflowRevisionFactory::new();
    }

    public function getTable(): string
    {
        return config('aitumalow.tables.revisions', 'aitumalow_workflow_revisions');
    }

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::workflow());
    }

    /** @return HasMany<WorkflowRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ConfiguredModels::run(), 'workflow_revision_id');
    }
}
