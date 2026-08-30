<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('add_workflow_node')]
#[Title('Add Workflow Node')]
#[Description('Add a registered workflow node to a draft workflow using its exact stable key. Configuration is validated against the schema returned by show_workflow_node.')]
final class AddWorkflowNodeTool extends Tool
{
    public function __construct(
        private readonly WorkflowService $service,
        private readonly NodeRegistry $registry,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('Workflow ID.'),
            'key' => $schema->string()->required()->description('Exact stable workflow node key.'),
            'name' => $schema->string()->description('Optional instance name; defaults to the registered node name.'),
            'config' => $schema->object()->description('Configuration matching the registered workflow node schema.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $key = $request->string('key')->toString();
        $definition = $this->registry->definition($key);
        $config = $request->get('config', []);

        if ($definition === null) {
            return Response::error("Workflow node [{$key}] is not registered.");
        }

        $node = $this->service->addNode(
            $request->integer('workflow_id'),
            $key,
            is_array($config) ? $config : [],
            $request->get('name', $definition['name']),
        );

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
