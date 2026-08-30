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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_workflow')]
#[Title('Update Workflow')]
#[Description('Update a workflow draft\'s name or description.')]
#[IsIdempotent]
class UpdateWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'name' => $schema->string()->description('New workflow name'),
            'description' => $schema->string()->description('New workflow description'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $data = array_filter([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
        ], fn ($value) => ! is_null($value));

        $workflow = $this->service->update($request->integer('workflow_id'), $data);

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
