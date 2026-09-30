<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Http\Resources\WorkflowTagResource;
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

#[Name('manage_workflow_tag')]
#[Description('Create, update or delete an editor tag. Uses the same validation as the UI. Deletion must be explicitly requested.')]
#[IsDestructive]
final class ManageWorkflowTagTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()->enum(['create', 'update', 'delete'])->required(),
            'id' => $schema->integer()->description('Required for update/delete.'),
            'name' => $schema->string(),
            'color' => $schema->string()->nullable()->description('Optional editor tag color.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['operation' => ['required', 'in:create,update,delete'], 'id' => ['required_unless:operation,create', 'integer', 'prohibited_if:operation,create']]);
            $service = app(WorkflowOrganizationService::class);
            $item = $data['operation'] === 'create' ? null : ConfiguredModels::tag()::query()->findOrFail((int) $data['id']);
            if ($data['operation'] === 'delete') {
                $service->deleteTag($item ?? throw new \LogicException('Tag ID is required.'));

                return ['message' => 'Tag deleted.'];
            }

            return ['tag' => new WorkflowTagResource($service->saveTag($request->only(['name', 'color']), $item))->resolve()];
        });
    }
}
