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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('control_workflow_run')]
#[Description('Cancel, resume or replay an execution using the same editor controls and runtime. Replay starts a new run pinned to the original revision, and may repeat business effects. Use only when requested.')]
#[IsDestructive]
final class ControlWorkflowRunTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_run_id' => $schema->integer()->required(),
            'action' => $schema->string()->enum(['cancel', 'resume', 'replay'])->required(),
            'payload' => $schema->array()->items($schema->object())->description('Resume input items only.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['workflow_run_id' => ['required', 'integer'], 'action' => ['required', 'in:cancel,resume,replay'],
                'payload' => ['sometimes', 'array', 'list', 'prohibited_unless:action,resume'], 'payload.*' => ['array']]);
            $service = app(WorkflowService::class);
            $run = match ($data['action']) {
                'cancel' => $service->cancel($data['workflow_run_id']),
                'resume' => $service->resume($data['workflow_run_id'], $data['payload'] ?? []),
                'replay' => $service->replay($data['workflow_run_id']),
                default => throw new \InvalidArgumentException('Unsupported run action.'),
            };

            return ['workflow_run' => ['id' => $run->id, 'workflow_id' => $run->workflow_id, 'workflow_revision_id' => $run->workflow_revision_id, 'status' => $run->status->value],
                'inspection' => ['tool' => 'show_workflow_run', 'workflow_run_id' => $run->id]];
        });
    }
}
