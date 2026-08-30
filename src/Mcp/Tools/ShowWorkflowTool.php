<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Models\Workflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_workflow')]
#[Title('Show Workflow')]
#[Description('Get detailed information about a workflow including all its nodes and edges.')]
#[IsReadOnly]
class ShowWorkflowTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $workflow = Workflow::with(['nodes', 'edges'])
            ->findOrFail($request->integer('workflow_id'));

        return Response::structured([
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
            ],
            'nodes' => $workflow->nodes->map(fn ($node) => [
                'id' => $node->id,
                'name' => $node->name,
                'key' => $node->node_key,
                'type' => $node->type->value,
                'config' => $node->config,
            ])->all(),
            'edges' => $workflow->edges->map(fn ($edge) => [
                'id' => $edge->id,
                'source_workflow_node_id' => $edge->source_node_id,
                'target_workflow_node_id' => $edge->target_node_id,
                'source_port' => $edge->source_port,
                'target_port' => $edge->target_port,
            ])->all(),
        ]);
    }
}
