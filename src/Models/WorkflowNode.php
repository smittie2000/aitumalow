<?php

namespace Aitumalow\Models;

use Aitumalow\Database\Factories\WorkflowNodeFactory;
use Aitumalow\Enums\NodeType;
use Aitumalow\Services\WorkflowService;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_id
 * @property NodeType $type
 * @property string $node_key
 * @property string|null $name
 * @property array<string, mixed> $config
 * @property array<string, mixed>|null $pinned_data
 * @property int $position_x
 * @property int $position_y
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 * @property-read Collection<int, WorkflowEdge> $outgoingEdges
 * @property-read Collection<int, WorkflowEdge> $incomingEdges
 * @property-read Collection<int, WorkflowNodeRun> $nodeRuns
 */
class WorkflowNode extends Model
{
    /** @use HasFactory<WorkflowNodeFactory> */
    use HasFactory;

    protected static function newFactory(): WorkflowNodeFactory
    {
        return WorkflowNodeFactory::new();
    }

    protected $guarded = [];

    public function getTable(): string
    {
        return config('aitumalow.tables.nodes', 'aitumalow_workflow_nodes');
    }

    protected function casts(): array
    {
        return [
            'type' => NodeType::class,
            'config' => 'array',
            'pinned_data' => 'array',
        ];
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredModels::workflow(),
        );
    }

    /** @return HasMany<WorkflowEdge, $this> */
    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::edge(),
            'source_node_id',
        );
    }

    /** @return HasMany<WorkflowEdge, $this> */
    public function incomingEdges(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::edge(),
            'target_node_id',
        );
    }

    /** @return HasMany<WorkflowNodeRun, $this> */
    public function nodeRuns(): HasMany
    {
        return $this->hasMany(
            ConfiguredModels::nodeRun(),
            'node_id',
        );
    }

    // ── Pinned Test Data ───────────────────────────────────────

    public function hasPinnedInput(): bool
    {
        return ! empty($this->pinned_data['input']);
    }

    public function hasPinnedOutput(): bool
    {
        return ! empty($this->pinned_data['output']);
    }

    /** @return array<int, array<string, mixed>>|null */
    public function getPinnedInput(): ?array
    {
        return $this->pinned_data['input'] ?? null;
    }

    /** @return array<string, array<int, array<string, mixed>>>|null */
    public function getPinnedOutput(): ?array
    {
        return $this->pinned_data['output'] ?? null;
    }

    // ── Fluent API ──────────────────────────────────────────────

    /** @param array<string, mixed> $config */
    public function addNode(string $name, string $nodeKey, array $config = []): self
    {
        return $this->service()->addNode($this->workflow_id, $nodeKey, $config, $name);
    }

    /**
     * Connect this node to a target node. Returns the TARGET node for chaining.
     */
    public function connect(
        int|self $target,
        string $sourcePort = 'main',
        string $targetPort = 'main',
    ): self {
        $targetNode = $target instanceof self ? $target : self::findOrFail($target);

        $this->service()->connect($this, $targetNode, $sourcePort, $targetPort);

        return $targetNode;
    }

    public function activate(): Workflow
    {
        return $this->workflow->activate();
    }

    public function deactivate(): Workflow
    {
        return $this->workflow->deactivate();
    }

    /** @param array<int, array<string, mixed>> $payload */
    public function run(array $payload = []): WorkflowRun
    {
        return $this->workflow->start($payload);
    }

    /** @return array<int, string> */
    public function validateGraph(): array
    {
        return $this->workflow->validateGraph();
    }

    private function service(): WorkflowService
    {
        return app(WorkflowService::class);
    }
}
