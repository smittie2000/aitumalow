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
            'search' => $schema->string()->description('Search names, descriptions, categories and stable keys.'),
            'include_schemas' => $schema->boolean()->default(false)->description('Include schemas and ports to compose a workflow without separate show calls.'),
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
                'input_ports' => $schema->array()->items($schema->string()),
                'output_ports' => $schema->array()->items($schema->string()),
                'config_schema' => $schema->array()->items($schema->object()),
                'output_schema' => $schema->object(),
            ]))->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $request->validate(['type' => ['nullable', 'string'], 'category' => ['nullable', 'string'], 'search' => ['nullable', 'string', 'max:200'], 'include_schemas' => ['sometimes', 'boolean']]);
        $search = mb_strtolower(trim($request->string('search')->toString()));
        $includeSchemas = $request->boolean('include_schemas');
        $type = $request->string('type')->toString();
        $category = $request->string('category')->toString();

        $nodes = collect($this->registry->all())
            ->when($type !== '', fn ($nodes) => $nodes->where('type', $type))
            ->when($category !== '', fn ($nodes) => $nodes->where('category', $category))
            ->when($search !== '', fn ($nodes) => $nodes->filter(fn (array $node): bool => str_contains(mb_strtolower(implode(' ', [$node['name'], $node['description'], $node['category'], $node['key']])), $search)))
            ->map(fn (array $node): array => [
                'key' => $node['key'],
                'name' => $node['name'],
                'category' => $node['category'],
                'icon' => $node['icon'],
                'description' => $node['description'],
                'type' => $node['type'],
                ...($includeSchemas ? array_intersect_key($node, array_flip(['input_ports', 'output_ports', 'config_schema', 'output_schema'])) : []),
            ])
            ->values()
            ->all();

        return Response::structured(['workflow_nodes' => $nodes]);
    }
}
