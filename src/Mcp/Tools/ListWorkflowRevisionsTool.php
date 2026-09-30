<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowRevisionResource;
use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workflow_revisions')]
#[Description('List published versions of a workflow, identifying the live version. Does not change the draft.')]
#[IsReadOnly]
final class ListWorkflowRevisionsTool extends Tool
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
            $workflow = ConfiguredModels::workflow()::query()->findOrFail((int) $data['workflow_id']);

            return ['active_revision_id' => $workflow->active_revision_id, 'revisions' => $workflow->revisions()->orderByDesc('version')->get()
                ->map(fn ($revision): array => new WorkflowRevisionResource($revision->setRelation('workflow', $workflow))->resolve())->all()];
        });
    }
}
