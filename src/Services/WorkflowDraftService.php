<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\DTOs\WorkflowDraftDefinition;
use Aitumalow\Engine\GraphValidator;
use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode;
use Illuminate\Support\Facades\DB;

final readonly class WorkflowDraftService
{
    public function __construct(
        private WorkflowService $workflows,
        private GraphValidator $validator,
    ) {}

    /**
     * @return array{
     *     workflow: array{id: int, name: string, description: string|null, is_active: bool, active_revision_id: int|null},
     *     draft_hash: string,
     *     nodes: list<array{id: string, capability: string, name: string|null, config: array<string, mixed>}>,
     *     edges: list<array{from: string, to: string, output: string, input: string}>
     * }
     */
    public function get(int|Workflow $workflow): array
    {
        $id = $workflow instanceof Workflow ? $workflow->id : $workflow;

        return $this->project(Workflow::query()->findOrFail($id));
    }

    /**
     * Atomically replace and validate the mutable draft without publishing it.
     *
     * @return array{
     *     workflow: array{id: int, name: string, description: string|null, is_active: bool, active_revision_id: int|null},
     *     draft_hash: string,
     *     nodes: list<array{id: string, capability: string, name: string|null, config: array<string, mixed>}>,
     *     edges: list<array{from: string, to: string, output: string, input: string}>
     * }
     */
    public function replace(
        int|Workflow $workflow,
        WorkflowDraftDefinition $draft,
        string $expectedDraftHash,
    ): array {
        $id = $workflow instanceof Workflow ? $workflow->id : $workflow;

        return DB::transaction(function () use ($id, $draft, $expectedDraftHash): array {
            $locked = Workflow::query()->lockForUpdate()->findOrFail($id);
            $current = $this->project($locked);

            if (! hash_equals($current['draft_hash'], $expectedDraftHash)) {
                throw new WorkflowDraftConflictException;
            }

            $locked->edges()->delete();
            $locked->nodes()->delete();

            /** @var array<string, WorkflowNode> $nodes */
            $nodes = [];
            foreach ($draft->nodes as $node) {
                $nodes[$node['id']] = $this->workflows->addNode(
                    $locked,
                    $node['capability'],
                    $node['config'],
                    $node['name'],
                );
            }

            foreach ($draft->edges as $edge) {
                $this->workflows->connect(
                    $nodes[$edge['from']],
                    $nodes[$edge['to']],
                    $edge['output'],
                    $edge['input'],
                );
            }

            $this->validator->validate($locked);

            return $this->project($locked);
        });
    }

    /**
     * @return array{
     *     workflow: array{id: int, name: string, description: string|null, is_active: bool, active_revision_id: int|null},
     *     draft_hash: string,
     *     nodes: list<array{id: string, capability: string, name: string|null, config: array<string, mixed>}>,
     *     edges: list<array{from: string, to: string, output: string, input: string}>
     * }
     */
    private function project(Workflow $workflow): array
    {
        $nodes = $workflow->nodes()->orderBy('id')->get();
        $aliases = [];
        $projectedNodes = [];

        foreach ($nodes->values() as $index => $node) {
            $alias = 'node_'.($index + 1);
            $aliases[$node->id] = $alias;
            $projectedNodes[] = [
                'id' => $alias,
                'capability' => $node->node_key,
                'name' => $node->name,
                'config' => $node->config ?? [],
            ];
        }

        $projectedEdges = [];
        foreach ($workflow->edges()->orderBy('id')->get() as $edge) {
            $projectedEdges[] = [
                'from' => $aliases[$edge->source_node_id],
                'to' => $aliases[$edge->target_node_id],
                'output' => $edge->source_port,
                'input' => $edge->target_port,
            ];
        }

        $definition = [
            'nodes' => $projectedNodes,
            'edges' => $projectedEdges,
        ];

        return [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
                'active_revision_id' => $workflow->active_revision_id,
            ],
            'draft_hash' => hash('sha256', json_encode(
                $this->canonicalize($definition),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            )),
            ...$definition,
        ];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
