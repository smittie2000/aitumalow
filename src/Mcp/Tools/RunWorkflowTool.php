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

#[Name('run_workflow')]
#[Title('Run Workflow')]
#[Description('Execute a workflow with an optional payload. The workflow must be active. Returns the run result with status and node execution details.')]
class RunWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'payload' => $schema->array()->description('Array of data items to pass to the workflow trigger. Each item is an object.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $run = $this->service->run(
            $request->integer('workflow_id'),
            $request->get('payload', []),
        );

        $run->load('nodeRuns.node');

        return Response::structured([
            'workflow_run' => [
                'id' => $run->id,
                'workflow_id' => $run->workflow_id,
                'status' => $run->status->value,
                'started_at' => $run->started_at,
                'finished_at' => $run->finished_at,
                'node_runs' => $run->nodeRuns->map(fn ($nr) => [
                    'workflow_node_id' => $nr->node_id,
                    'workflow_node_name' => $nr->node->name,
                    'workflow_node_key' => $nr->node->node_key,
                    'status' => $nr->status->value,
                    'duration_ms' => $nr->duration_ms,
                    'error_message' => $nr->error_message,
                ])->all(),
            ],
        ]);
    }
}
