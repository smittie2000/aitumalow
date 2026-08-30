<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Models\WorkflowNode;
use Aitumalow\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('connect_workflow_nodes')]
#[Title('Connect Workflow Nodes')]
#[Description('Connect two stored workflow node instances in the same workflow. Ports default to main.')]
final class ConnectWorkflowNodesTool extends Tool
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'source_workflow_node_id' => $schema->integer()->required(),
            'target_workflow_node_id' => $schema->integer()->required(),
            'source_port' => $schema->string()->default('main'),
            'target_port' => $schema->string()->default('main'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $source = WorkflowNode::findOrFail($request->integer('source_workflow_node_id'));
        $target = WorkflowNode::findOrFail($request->integer('target_workflow_node_id'));

        if ($source->workflow_id !== $target->workflow_id) {
            throw ValidationException::withMessages([
                'target_workflow_node_id' => 'Workflow nodes must belong to the same workflow.',
            ]);
        }

        $edge = $this->service->connect(
            $source,
            $target,
            $request->string('source_port', 'main')->toString(),
            $request->string('target_port', 'main')->toString(),
        );

        return Response::structured(['workflow_edge' => [
            'id' => $edge->id,
            'workflow_id' => $edge->workflow_id,
            'source_workflow_node_id' => $edge->source_node_id,
            'target_workflow_node_id' => $edge->target_node_id,
            'source_port' => $edge->source_port,
            'target_port' => $edge->target_port,
        ]]);
    }
}
