<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;

/** The mutable editor document; never used to execute a workflow. */
final class WorkflowGraphSnapshot
{
    /** @return array<string, mixed> */
    public function capture(Workflow $workflow): array
    {
        $workflow->load(['nodes' => fn ($query) => $query->orderBy('id'), 'edges' => fn ($query) => $query->orderBy('id')]);

        return [
            'nodes' => $workflow->nodes->map(fn (WorkflowNode $node): array => [
                'id' => $node->id,
                'type' => $node->type->value,
                'node_key' => $node->node_key,
                'name' => $node->name,
                'config' => $node->config,
                'pinned_data' => $node->pinned_data,
                'position_x' => $node->position_x,
                'position_y' => $node->position_y,
            ])->all(),
            'edges' => $workflow->edges->map(fn (WorkflowEdge $edge): array => $edge->only([
                'id', 'source_node_id', 'source_port', 'target_node_id', 'target_port',
            ]))->all(),
        ];
    }

    public function hash(mixed $value): string
    {
        return hash('sha256', json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonicalize(...), $value);
    }
}
