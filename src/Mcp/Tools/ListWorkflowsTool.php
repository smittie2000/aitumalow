<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Models\Workflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workflows')]
#[Title('List Workflows')]
#[Description('List all workflows with their status. Returns id, name, active status, and node/edge counts.')]
#[IsReadOnly]
class ListWorkflowsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('Page number')->default(1),
            'per_page' => $schema->integer()->description('Items per page')->default(15),
            'search' => $schema->string()->description('Search workflows by name'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 15);

        $query = Workflow::withCount(['nodes', 'edges']);

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn (Workflow $w) => [
            'id' => $w->id,
            'name' => $w->name,
            'description' => $w->description,
            'is_active' => $w->is_active,
            'nodes_count' => $w->nodes_count,
            'edges_count' => $w->edges_count,
        ])->all();

        return Response::structured([
            'workflows' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
