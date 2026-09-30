<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowRunResource;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_workflow_run')]
#[Title('Show Workflow Run')]
#[Description('Inspect one workflow run, including its current status, waiting state, command outcomes, and bounded node diagnostics. Set include_data to inspect the same input/output samples available in the editor. Summaries omit payloads by default.')]
#[IsReadOnly]
final class ShowWorkflowRunTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_data' => $schema->boolean()->default(false)->description('Include editor input/output samples; these may contain host data.'),
            'workflow_run_id' => $schema->integer()->required()->description('The Aitumalow workflow run ID'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $request->validate(['workflow_run_id' => ['required', 'integer'], 'include_data' => ['sometimes', 'boolean']]);
        $run = ConfiguredModels::run()::query()
            ->findOrFail($request->integer('workflow_run_id'))
            ->load(['nodeRuns.node', 'commands']);

        return Response::structured([
            'workflow_run' => $request->boolean('include_data') ? new WorkflowRunResource($run)->resolve() : $this->workflowRun($run),
        ]);
    }

    /** @return array<string, mixed> */
    private function workflowRun(WorkflowRun $run): array
    {
        return [
            'id' => $run->id,
            'workflow_id' => $run->workflow_id,
            'status' => $run->status->value,
            'workflow_revision_id' => $run->workflow_revision_id,
            'waiting_node_id' => $run->waiting_node_id,
            'waiting_state' => $run->waiting_state,
            'error_message' => $run->error_message,
            'started_at' => $run->started_at,
            'finished_at' => $run->finished_at,
            'node_runs' => $run->nodeRuns->map(static fn ($nodeRun): array => [
                'id' => $nodeRun->id,
                'workflow_node_id' => $nodeRun->node_id,
                'workflow_node_name' => $nodeRun->node?->name,
                'workflow_node_key' => $nodeRun->node?->node_key,
                'status' => $nodeRun->status->value,
                'attempts' => $nodeRun->attempts,
                'duration_ms' => $nodeRun->duration_ms,
                'error_message' => $nodeRun->error_message,
            ])->all(),
            'commands' => $run->commands->map(static fn ($command): array => [
                'name' => $command->name,
                'status' => $command->status->value,
                'error_message' => $command->error_message,
                'applied_at' => $command->applied_at,
            ])->all(),
        ];
    }
}
