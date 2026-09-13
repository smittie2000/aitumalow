<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowResource;
use Aitumalow\Services\WorkflowGraphService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_workflow_graph')]
#[Description('Read the editor graph with persistent node/edge IDs, positions, pinned samples and a concurrency hash for edit_workflow_graph. This hash is distinct from get_workflow_draft aliases.')]
#[IsReadOnly]
final class GetWorkflowGraphTool extends Tool
{
    public function __construct(private readonly WorkflowGraphService $graphs) {}

    public function schema(JsonSchema $schema): array
    {
        return ['workflow_id' => $schema->integer()->required()];
    }

    public function handle(Request $request): ResponseFactory
    {
        $graph = $this->graphs->get($request->integer('workflow_id'));

        return Response::structured([...$graph, 'workflow' => new WorkflowResource($graph['workflow'])->resolve()]);
    }
}
