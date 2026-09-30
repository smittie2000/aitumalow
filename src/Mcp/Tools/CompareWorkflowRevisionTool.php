<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowRevisionResource;
use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Runtime\WorkflowSnapshot;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('compare_workflow_revision')]
#[Description('Review an immutable published version beside the current draft, exactly as the editor version comparison does. Revision must belong to the workflow.')]
#[IsReadOnly]
final class CompareWorkflowRevisionTool extends Tool
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
            $revision = $workflow->revisions()->findOrFail((int) $data['revision_id']);
            $snapshots = app(WorkflowSnapshot::class);

            return ['revision' => new WorkflowRevisionResource($revision->setRelation('workflow', $workflow))->resolve(), 'revision_definition' => $snapshots->fromRevision($revision),
                'draft_definition' => $snapshots->captureDraft($workflow)];
        });
    }
}
