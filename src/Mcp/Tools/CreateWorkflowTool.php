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

#[Name('create_workflow')]
#[Title('Create Workflow')]
#[Description('Create an empty draft workflow. Add registered nodes with add_workflow_node and connect them with connect_workflow_nodes.')]
class CreateWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Workflow name'),
            'description' => $schema->string()->description('Workflow description'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $data = array_filter([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
        ], fn ($v) => ! is_null($v));

        $workflow = $this->service->create($data);

        return Response::structured([
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
            ],
        ]);
    }
}
