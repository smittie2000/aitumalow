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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('validate_workflow')]
#[Title('Validate Workflow')]
#[Description('Validate a workflow\'s graph structure. Returns validation errors if any. A valid workflow has exactly one trigger, all nodes connected, no cycles, and valid port connections.')]
#[IsReadOnly]
class ValidateWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $errors = $this->service->validate($request->integer('workflow_id'));

        return Response::structured([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
}
