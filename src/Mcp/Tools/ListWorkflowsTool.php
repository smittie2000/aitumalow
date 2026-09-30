<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Services\WorkflowListingService;
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
            'sort' => $schema->string()->enum(['name', 'created_at', 'updated_at']),
            'direction' => $schema->string()->enum(['asc', 'desc']),
            'active_only' => $schema->boolean(),
            'folder_id' => $schema->integer(),
            'uncategorized' => $schema->boolean(),
            'tag_id' => $schema->integer(),
            'search' => $schema->string()->description('Search workflows by name'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'search' => ['sometimes', 'string'], 'sort' => ['sometimes', 'in:name,created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'], 'active_only' => ['sometimes', 'boolean'],
            'folder_id' => ['sometimes', 'integer'], 'uncategorized' => ['sometimes', 'boolean'], 'tag_id' => ['sometimes', 'integer'],
        ]);
        $paginator = app(WorkflowListingService::class)->query($filters)->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
        $items = collect($paginator->items())->map(fn (Workflow $workflow): array => new WorkflowResource($workflow)->resolve())->all();

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
