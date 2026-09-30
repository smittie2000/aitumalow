<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Services\WorkflowVariableService;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_workflow_variables')]
#[Description('Discover usable expression paths and functions for one step, exactly as the editor variable panel does. Node must belong to the workflow.')]
#[IsReadOnly]
final class GetWorkflowVariablesTool extends Tool
{
    use HandlesWorkflowErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required(),
            'node_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $data = $request->validate(['workflow_id' => ['required', 'integer'], 'node_id' => ['required', 'integer']]);
            $workflow = ConfiguredModels::workflow()::query()->findOrFail((int) $data['workflow_id']);

            return app(WorkflowVariableService::class)->available($workflow, $workflow->nodes()->findOrFail((int) $data['node_id']));
        });
    }
}
