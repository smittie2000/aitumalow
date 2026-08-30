<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Requests\StoreNodeRequest;
use Aitumalow\Http\Requests\UpdateNodeRequest;
use Aitumalow\Http\Resources\WorkflowNodeResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WorkflowNodeController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
        private readonly NodeRegistry $registry,
    ) {}

    public function store(StoreNodeRequest $request, Workflow $workflow): WorkflowNodeResource
    {
        $node = $this->service->addNode(
            workflow: $workflow,
            nodeKey: $request->validated('node_key'),
            config: $request->validated('config', []),
            name: $request->validated('name'),
        );

        if ($request->has('position_x')) {
            $node->update([
                'position_x' => $request->integer('position_x'),
                'position_y' => $request->integer('position_y'),
            ]);
        }

        return new WorkflowNodeResource($node);
    }

    public function update(UpdateNodeRequest $request, Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $node = $this->service->updateNode($node, $request->validated());

        return new WorkflowNodeResource($node);
    }

    public function destroy(Workflow $workflow, WorkflowNode $node): JsonResponse
    {
        $this->service->removeNode($node->id);

        return response()->json(['message' => 'Node deleted.'], 200);
    }

    public function position(Request $request, Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $request->validate([
            'position_x' => ['required', 'integer'],
            'position_y' => ['required', 'integer'],
        ]);

        $node->update([
            'position_x' => $request->integer('position_x'),
            'position_y' => $request->integer('position_y'),
        ]);

        return new WorkflowNodeResource($node->fresh());
    }

    public function availableVariables(Workflow $workflow, WorkflowNode $node): JsonResponse
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

        return response()->json([
            'globals' => [
                ['path' => 'item', 'type' => 'object', 'label' => 'Current Item', 'children' => [
                    ['path' => 'item.*', 'type' => 'mixed', 'label' => 'Any field from current item'],
                ]],
                ['path' => 'payload', 'type' => 'object', 'label' => 'Initial Payload'],
                ['path' => 'trigger', 'type' => 'array', 'label' => 'Trigger Output'],
            ],
            'nodes' => $upstreamNodes,
            'functions' => $this->getAvailableFunctions(),
        ]);
    }

    public function pin(Request $request, Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $request->validate([
            'source' => ['required', 'string', 'in:run,manual'],
            'node_run_id' => ['required_if:source,run', 'integer'],
            'input' => ['nullable', 'array'],
            'output' => ['nullable', 'array'],
        ]);

        if ($request->input('source') === 'run') {
            $nodeRun = WorkflowNodeRun::findOrFail($request->integer('node_run_id'));

            abort_unless($nodeRun->node_id === $node->id, 422, 'Node run does not belong to this node.');

            $pinnedData = [
                'input' => $nodeRun->input,
                'output' => $nodeRun->output,
                'source_run_id' => $nodeRun->workflow_run_id,
            ];
        } else {
            $pinnedData = array_filter([
                'input' => $request->input('input'),
                'output' => $request->input('output'),
            ], fn ($v) => $v !== null);
        }

        $node->update(['pinned_data' => $pinnedData]);

        return new WorkflowNodeResource($node->fresh());
    }

    public function unpin(Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $node->update(['pinned_data' => null]);

        return new WorkflowNodeResource($node->fresh());
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
