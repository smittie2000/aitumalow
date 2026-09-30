<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowFolderResource;
use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Services\WorkflowOrganizationService;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('manage_workflow_folder')]
#[Description('Create, update or delete an editor folder. Uses the same validation as the UI. Deletion must be explicitly requested. Cannot create folder cycles or delete a nonempty folder.')]
#[IsDestructive]
final class ManageWorkflowFolderTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()->enum(['create', 'update', 'delete'])->required(),
            'id' => $schema->integer()->description('Required for update/delete.'),
            'name' => $schema->string(),
            'parent_id' => $schema->integer()->nullable()->description('Parent folder; null moves to the root.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['operation' => ['required', 'in:create,update,delete'], 'id' => ['required_unless:operation,create', 'integer', 'prohibited_if:operation,create']]);
            $service = app(WorkflowOrganizationService::class);
            $item = $data['operation'] === 'create' ? null : ConfiguredModels::folder()::query()->findOrFail((int) $data['id']);
            if ($data['operation'] === 'delete') {
                $service->deleteFolder($item ?? throw new \LogicException('Folder ID is required.'));

                return ['message' => 'Folder deleted.'];
            }

            return ['folder' => new WorkflowFolderResource($service->saveFolder($request->only(['name', 'parent_id']), $item))->resolve()];
        });
    }
}
