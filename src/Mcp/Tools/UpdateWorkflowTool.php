<?php

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Requests\UpdateWorkflowRequest;
use Aitumalow\Http\Resources\WorkflowResource;
use Aitumalow\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_workflow')]
#[Title('Update Workflow')]
#[Description('Update a workflow draft\'s metadata, settings, folder and tags using the same validation as the editor. Does not publish.')]
#[IsIdempotent]
class UpdateWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'name' => $schema->string()->description('New workflow name'),
            'description' => $schema->string()->nullable()->description('New workflow description; null clears it.'),
            'settings' => $schema->object()->nullable()->description('Replacement workflow settings. Preserve unrelated settings.'),
            'folder_id' => $schema->integer()->nullable()->description('Folder ID; null moves to Unfiled.'),
            'tag_ids' => $schema->array()->items($schema->integer())->description('Replacement tag IDs; empty removes all tags.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $request->validate(['workflow_id' => ['required', 'integer']]);
        $data = Validator::make($request->only(['name', 'description', 'settings', 'folder_id', 'tag_ids']), new UpdateWorkflowRequest()->rules())->validate();

        $workflow = $this->service->update($request->integer('workflow_id'), $data);

        return Response::structured([
            'workflow' => new WorkflowResource($workflow->load(['tags', 'folder', 'activeRevision']))->resolve(),
        ]);
    }
}
