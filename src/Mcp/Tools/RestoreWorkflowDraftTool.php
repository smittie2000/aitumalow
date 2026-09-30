<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Services\WorkflowDraftService;
use Aitumalow\Services\WorkflowService;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('restore_workflow_draft')]
#[Description('Restore a published version into the mutable draft using the editor service. Replaces draft edits; live version and existing runs stay unchanged until explicit publication.')]
#[IsDestructive]
final class RestoreWorkflowDraftTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required(),
            'revision_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['workflow_id' => ['required', 'integer'], 'revision_id' => ['required', 'integer']]);
            $workflow = ConfiguredModels::workflow()::query()->findOrFail((int) $data['workflow_id']);
            app(WorkflowService::class)->restoreDraft($workflow, $workflow->revisions()->findOrFail((int) $data['revision_id']));

            return ['draft' => app(WorkflowDraftService::class)->get($workflow)];
        });
    }
}
