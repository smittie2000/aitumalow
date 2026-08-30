<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Models\WorkflowNode as WorkflowNodeModel;
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

#[Name('update_workflow_node')]
#[Title('Update Workflow Node')]
#[Description('Update a stored workflow node instance. Configuration is replaced and validated against its registered workflow node schema.')]
#[IsIdempotent]
final class UpdateWorkflowNodeTool extends Tool
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_node_id' => $schema->integer()->required()->description('Stored workflow node ID.'),
            'name' => $schema->string()->description('New instance name.'),
            'config' => $schema->object()->description('Complete replacement configuration.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $node = WorkflowNodeModel::findOrFail($request->integer('workflow_node_id'));
        $data = [];

        if ($request->get('name') !== null) {
            $data['name'] = $request->string('name')->toString();
        }

        if ($request->get('config') !== null) {
            $config = $request->get('config');
            $data['config'] = is_array($config) ? $config : [];
        }

        $node = $this->service->updateNode($node, $data);

        return Response::structured(['workflow_node' => [
            'id' => $node->id,
            'workflow_id' => $node->workflow_id,
            'key' => $node->node_key,
            'name' => $node->name,
            'type' => $node->type->value,
            'config' => $node->config,
        ]]);
    }
}
