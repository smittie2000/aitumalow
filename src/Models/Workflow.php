<?php

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowFactory;
use Aitumalow\Enums\CreatedVia;
use Aitumalow\Enums\NodeType;
use Aitumalow\Services\WorkflowService;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $active_revision_id
 * @property array<string, mixed>|null $settings
 * @property CreatedVia|null $created_via
 * @property int|null $folder_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, WorkflowNode> $nodes
 * @property-read Collection<int, WorkflowEdge> $edges
 * @property-read Collection<int, WorkflowRun> $runs
 * @property-read Collection<int, WorkflowRevision> $revisions
 * @property-read WorkflowRevision|null $activeRevision
 * @property-read Collection<int, WorkflowTag> $tags
 * @property-read WorkflowFolder|null $folder
 * @property-read int|null $nodes_count
 * @property-read int|null $edges_count
 */
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function newFactory(): WorkflowFactory
    {
        return WorkflowFactory::new();
    }

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(static function (self $workflow): void {
            if (! filled($workflow->key)) {
                $workflow->key = 'workflow_'.Str::lower((string) Str::ulid());
            }
        });
    }

    public function getTable(): string
    {
        return config('aitumalow.tables.workflows', 'aitumalow_workflows');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'created_via' => CreatedVia::class,
        ];
    }

    /** @return HasMany<WorkflowNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::node(),
        );
    }

    /** @return HasMany<WorkflowEdge, $this> */
    public function edges(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::edge(),
        );
    }

    /** @return HasMany<WorkflowGraphEdit, $this> */
    public function graphEdits(): HasMany
    {
        return $this->hasMany(ConfiguredModels::graphEdit());
    }

    /** @return HasMany<WorkflowRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::run(),
        );
    }

    /** @return HasMany<WorkflowRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ConfiguredModels::revision());
    }

    /** @return BelongsTo<WorkflowRevision, $this> */
    public function activeRevision(): BelongsTo
    {
        return $this->belongsTo(ConfiguredModels::revision(), 'active_revision_id');
    }

    /** @return BelongsToMany<WorkflowTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ConfiguredModels::tag(),
            config('aitumalow.tables.tag_pivot', 'aitumalow_workflow_tag_pivot'),
            'workflow_id',
            'tag_id',
        );
    }

    /** @return BelongsTo<WorkflowFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::folder(),
            'folder_id',
        );
    }

    /**
     * Get the single trigger node for this workflow.
     */
    public function triggerNode(): ?WorkflowNode
    {
        return $this->nodes()->where('type', NodeType::Trigger)->first();
    }

    // ── Fluent API ──────────────────────────────────────────────

    /** @param array<string, mixed> $config */
    public function addNode(string $name, string $nodeKey, array $config = []): WorkflowNode
    {
        return $this->service()->addNode($this, $nodeKey, $config, $name);
    }

    public function connect(
        int|WorkflowNode $source,
        int|WorkflowNode $target,
        string $sourcePort = 'main',
        string $targetPort = 'main',
    ): WorkflowEdge {
        return $this->service()->connect($source, $target, $sourcePort, $targetPort);
    }

    public function activate(): static
    {
        $this->service()->activate($this);
        $this->refresh();

        return $this;
    }

    public function deactivate(): static
    {
        $this->service()->deactivate($this);
        $this->refresh();

        return $this;
    }

    public function restoreDraft(int|WorkflowRevision $revision): static
    {
        $this->service()->restoreDraft($this, $revision);
        $this->refresh();

        return $this;
    }

    /** @return array<int, string> */
    public function validateGraph(): array
    {
        return $this->service()->validate($this);
    }

    /** @param array<int, array<string, mixed>> $payload */
    public function start(array $payload = []): WorkflowRun
    {
        return $this->service()->run($this, $payload);
    }

    public function duplicate(): self
    {
        return $this->service()->duplicate($this);
    }

    public function removeNode(int $nodeId): void
    {
        $this->service()->removeNode($nodeId);
    }

    public function removeEdge(int $edgeId): void
    {
        $this->service()->removeEdge($edgeId);
    }

    /** @param array<int, int> $tagIds */
    public function attachTags(array $tagIds): static
    {
        $this->tags()->sync($tagIds);
        $this->load('tags');

        return $this;
    }

    /** @param array<int, int> $tagIds */
    public function detachTags(array $tagIds = []): static
    {
        if (empty($tagIds)) {
            $this->tags()->detach();
        } else {
            $this->tags()->detach($tagIds);
        }

        $this->load('tags');

        return $this;
    }

    public function moveToFolder(int|WorkflowFolder|null $folder): static
    {
        $folderId = $folder instanceof WorkflowFolder ? $folder->id : $folder;
        $this->update(['folder_id' => $folderId]);
        $this->load('folder');

        return $this;
    }

    private function service(): WorkflowService
    {
        return app(WorkflowService::class);
    }
}
