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
#[Description('Start an active workflow asynchronously with an optional payload. Returns a run handle and initial status; call show_workflow_run to inspect progress and node outcomes.')]
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

        return Response::structured([
            'workflow_run' => [
                'id' => $run->id,
                'workflow_id' => $run->workflow_id,
                'status' => $run->status->value,
                'started_at' => $run->started_at,
                'finished_at' => $run->finished_at,
            ],
            'inspection' => ['tool' => 'show_workflow_run', 'workflow_run_id' => $run->id],
        ]);
    }
}
