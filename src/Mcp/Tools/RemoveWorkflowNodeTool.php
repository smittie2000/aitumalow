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

#[Name('remove_workflow_node')]
#[Title('Remove Workflow Node')]
#[Description('Remove a stored workflow node instance and its connected edges.')]
#[IsDestructive]
final class RemoveWorkflowNodeTool extends Tool
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_node_id' => $schema->integer()->required()->description('Stored workflow node ID.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $id = $request->integer('workflow_node_id');
        $this->service->removeNode($id);

        return Response::structured(['removed_workflow_node_id' => $id]);
    }
}
