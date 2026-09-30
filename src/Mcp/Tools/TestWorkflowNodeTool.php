<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('test_workflow_node')]
#[Description('Test the current draft through a selected node, using the same real execution and pinned samples as Test step in the editor. May execute upstream business actions. Creates an execution snapshot without changing the live version or activation. Inspect its returned run with show_workflow_run; include_data reveals input/output.')]
final class TestWorkflowNodeTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required(),
            'node_id' => $schema->integer()->required(),
            'payload' => $schema->array()->items($schema->object())->description('Optional input items.'),
            'expected_graph_hash' => $schema->string()->description('Hash from get_workflow_graph; prevents testing a changed draft.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['workflow_id' => ['required', 'integer'], 'node_id' => ['required', 'integer'],
                'payload' => ['sometimes', 'array', 'list'], 'payload.*' => ['array'], 'expected_graph_hash' => ['sometimes', 'string', 'size:64']]);
            $run = app(WorkflowService::class)->testNode($data['workflow_id'], $data['node_id'], $data['payload'] ?? [], $data['expected_graph_hash'] ?? null);

            return ['workflow_run' => ['id' => $run->id, 'workflow_id' => $run->workflow_id, 'status' => $run->status->value],
                'inspection' => ['tool' => 'show_workflow_run', 'workflow_run_id' => $run->id]];
        });
    }
}
