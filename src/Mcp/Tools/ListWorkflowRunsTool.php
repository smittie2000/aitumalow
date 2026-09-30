<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Enums\RunStatus;
use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workflow_runs')]
#[Description('List execution history for a workflow, including draft tests. Filter by status and inspect a run with show_workflow_run.')]
#[IsReadOnly]
final class ListWorkflowRunsTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required(),
            'status' => $schema->string()->enum(array_column(RunStatus::cases(), 'value')),
            'page' => $schema->integer()->default(1),
            'per_page' => $schema->integer()->default(15),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['workflow_id' => ['required', 'integer'], 'status' => ['sometimes', Rule::enum(RunStatus::class)],
                'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100']]);
            $workflow = ConfiguredModels::workflow()::query()->findOrFail((int) $data['workflow_id']);
            $page = $workflow->runs()->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
                ->latest()->paginate($data['per_page'] ?? 15, ['*'], 'page', $data['page'] ?? 1);

            return ['workflow_runs' => collect($page->items())->map(fn ($run): array => [
                'id' => $run->id, 'workflow_id' => $run->workflow_id, 'workflow_revision_id' => $run->workflow_revision_id,
                'status' => $run->status->value, 'waiting_node_id' => $run->waiting_node_id, 'waiting_state' => $run->waiting_state,
                'error_message' => $run->error_message, 'started_at' => $run->started_at, 'finished_at' => $run->finished_at,
            ])->all(), 'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]];
        });
    }
}
