<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Registry\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_workflow_node')]
#[Title('Show Workflow Node')]
#[Description('Show one registered workflow node definition by its exact stable key, including configuration schema, ports, and output schema.')]
#[IsReadOnly]
final class ShowWorkflowNodeTool extends Tool
{
    public function __construct(
        private readonly NodeRegistry $registry,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required()->description('Exact stable workflow node key, for example app.ticket.create.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'workflow_node' => $schema->object([
                'key' => $schema->string()->required(),
                'name' => $schema->string()->required(),
                'category' => $schema->string()->required(),
                'icon' => $schema->string()->required(),
                'description' => $schema->string()->required(),
                'type' => $schema->string()->required(),
                'input_ports' => $schema->array()->items($schema->string())->required(),
                'output_ports' => $schema->array()->items($schema->string())->required(),
                'config_schema' => $schema->array()->items($schema->object())->required(),
                'output_schema' => $schema->object()->required(),
            ])->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $key = $request->string('key')->toString();
        $definition = $this->registry->definition($key);

        if ($definition === null) {
            return Response::error("Workflow node [{$key}] is not registered.");
        }

        unset($definition['documentation']);

        return Response::structured(['workflow_node' => $definition]);
    }
}
