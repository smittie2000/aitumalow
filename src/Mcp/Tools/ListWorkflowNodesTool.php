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

#[Name('list_workflow_nodes')]
#[Title('List Workflow Nodes')]
#[Description('List the host-approved workflow node definitions available for composition. Returns stable keys and presentation metadata; use show_workflow_node for schemas and ports.')]
#[IsReadOnly]
final class ListWorkflowNodesTool extends Tool
{
    public function __construct(
        private readonly NodeRegistry $registry,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Optional node type filter, such as trigger or action.'),
            'category' => $schema->string()->description('Optional exact category filter, such as Support or Billing.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'workflow_nodes' => $schema->array()->items($schema->object([
                'key' => $schema->string()->required(),
                'name' => $schema->string()->required(),
                'category' => $schema->string()->required(),
                'icon' => $schema->string()->required(),
                'description' => $schema->string()->required(),
                'type' => $schema->string()->required(),
            ]))->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $type = $request->string('type')->toString();
        $category = $request->string('category')->toString();

        $nodes = collect($this->registry->all())
            ->when($type !== '', fn ($nodes) => $nodes->where('type', $type))
            ->when($category !== '', fn ($nodes) => $nodes->where('category', $category))
            ->map(fn (array $node): array => [
                'key' => $node['key'],
                'name' => $node['name'],
                'category' => $node['category'],
                'icon' => $node['icon'],
                'description' => $node['description'],
                'type' => $node['type'],
            ])
            ->values()
            ->all();

        return Response::structured(['workflow_nodes' => $nodes]);
    }
}
