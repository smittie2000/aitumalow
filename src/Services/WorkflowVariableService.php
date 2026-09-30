<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Registry\NodeRegistry;

final readonly class WorkflowVariableService
{
    public function __construct(private NodeRegistry $registry) {}

    /** @return array<string, mixed> */
    public function available(Workflow $workflow, WorkflowNode $node): array
    {
        $nodes = $workflow->nodes()->get()->keyBy('id');
        $edges = $workflow->edges()->get();

        // BFS backward from target node to find all upstream nodes
        $reverseAdj = [];
        foreach ($edges as $edge) {
            $reverseAdj[$edge->target_node_id][] = $edge->source_node_id;
        }

        $upstreamIds = [];
        $queue = [$node->id];
        while (! empty($queue)) {
            $current = array_shift($queue);
            foreach ($reverseAdj[$current] ?? [] as $parentId) {
                if (! isset($upstreamIds[$parentId])) {
                    $upstreamIds[$parentId] = true;
                    $queue[] = $parentId;
                }
            }
        }

        // Build upstream nodes with their output schemas
        $upstreamNodes = [];
        foreach ($upstreamIds as $nodeId => $_) {
            $upNode = $nodes->get($nodeId);
            if (! $upNode || ! $this->registry->has($upNode->node_key)) {
                continue;
            }

            $outputSchema = $this->registry->definition($upNode->node_key)['output_schema'] ?? [];
            $nodeName = $upNode->name ?: $upNode->node_key;

            $variables = [];
            foreach ($outputSchema as $port => $fields) {
                foreach ($fields as $field) {
                    if ($field['key'] === '*') {
                        continue;
                    }
                    $variables[] = [
                        'path' => "nodes.{$nodeName}.{$port}.0.{$field['key']}",
                        'type' => $field['type'],
                        'label' => $field['label'],
                    ];
                }
            }

            $upstreamNodes[] = [
                'node_id' => $nodeId,
                'node_name' => $nodeName,
                'node_key' => $upNode->node_key,
                'variables' => $variables,
            ];
        }

        return [
            'globals' => [
                ['path' => 'item', 'type' => 'object', 'label' => 'Current Item', 'children' => [
                    ['path' => 'item.*', 'type' => 'mixed', 'label' => 'Any field from current item'],
                ]],
                ['path' => 'payload', 'type' => 'object', 'label' => 'Initial Payload'],
                ['path' => 'trigger', 'type' => 'array', 'label' => 'Trigger Output'],
            ],
            'nodes' => $upstreamNodes,
            'functions' => $this->getAvailableFunctions(),
        ];
    }

    /** @return array<int, array{name: string, args: string, label: string}> */
    private function getAvailableFunctions(): array
    {
        return [
            ['name' => 'upper', 'args' => 'value', 'label' => 'Uppercase'],
            ['name' => 'lower', 'args' => 'value', 'label' => 'Lowercase'],
            ['name' => 'trim', 'args' => 'value', 'label' => 'Trim whitespace'],
            ['name' => 'length', 'args' => 'value', 'label' => 'String length / Array count'],
            ['name' => 'contains', 'args' => 'haystack, needle', 'label' => 'Contains substring'],
            ['name' => 'replace', 'args' => 'search, replace, subject', 'label' => 'Replace text'],
            ['name' => 'split', 'args' => 'separator, string', 'label' => 'Split string'],
            ['name' => 'join', 'args' => 'glue, array', 'label' => 'Join array'],
            ['name' => 'round', 'args' => 'value, precision?', 'label' => 'Round number'],
            ['name' => 'abs', 'args' => 'value', 'label' => 'Absolute value'],
            ['name' => 'min', 'args' => '...values', 'label' => 'Minimum'],
            ['name' => 'max', 'args' => '...values', 'label' => 'Maximum'],
            ['name' => 'sum', 'args' => 'array', 'label' => 'Sum array'],
            ['name' => 'count', 'args' => 'array', 'label' => 'Count items'],
            ['name' => 'first', 'args' => 'array', 'label' => 'First element'],
            ['name' => 'last', 'args' => 'array', 'label' => 'Last element'],
            ['name' => 'pluck', 'args' => 'array, key', 'label' => 'Pluck field from array'],
            ['name' => 'now', 'args' => '', 'label' => 'Current datetime'],
            ['name' => 'date_format', 'args' => 'date, format', 'label' => 'Format date'],
            ['name' => 'int', 'args' => 'value', 'label' => 'Cast to integer'],
            ['name' => 'string', 'args' => 'value', 'label' => 'Cast to string'],
            ['name' => 'json_encode', 'args' => 'value', 'label' => 'JSON encode'],
            ['name' => 'json_decode', 'args' => 'value', 'label' => 'JSON decode'],
        ];
    }
}
