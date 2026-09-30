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

#[Name('delete_workflow')]
#[Description('Delete a workflow through the same service as the editor. Pauses its schedule. Only use when the user requests deletion.')]
#[IsDestructive]
final class DeleteWorkflowTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['workflow_id' => ['required', 'integer']]);
            app(WorkflowService::class)->delete($data['workflow_id']);

            return ['message' => 'Workflow deleted.'];
        });
    }
}
