<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowFolderResource;
use Aitumalow\Http\Resources\WorkflowTagResource;
use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Services\WorkflowOrganizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workflow_organization')]
#[Description('List the folders and tags available in the editor, including folder parent IDs, workflow counts and tag colors. Assign them with create_workflow or update_workflow.')]
#[IsReadOnly]
final class ListWorkflowOrganizationTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'tree' => $schema->boolean()->default(false),
            'search' => $schema->string()->description('Optional tag-name search.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $request->validate(['tree' => ['sometimes', 'boolean'], 'search' => ['sometimes', 'string']]);
            $service = app(WorkflowOrganizationService::class);

            return ['folders' => $service->folders($request->boolean('tree'))->map(fn ($folder): array => new WorkflowFolderResource($folder)->resolve())->all(),
                'tags' => $service->tags($request->string('search')->toString())->map(fn ($tag): array => new WorkflowTagResource($tag)->resolve())->all()];
        });
    }
}
