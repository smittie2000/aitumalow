<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('disconnect_workflow_nodes')]
#[Title('Disconnect Workflow Nodes')]
#[Description('Remove one stored workflow edge between workflow node instances.')]
#[IsDestructive]
final class DisconnectWorkflowNodesTool extends Tool
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_edge_id' => $schema->integer()->required()->description('Stored workflow edge ID.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $id = $request->integer('workflow_edge_id');
        $this->service->removeEdge($id);

        return Response::structured(['removed_workflow_edge_id' => $id]);
    }
}
