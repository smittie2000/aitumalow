<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\DTOs\WorkflowDraftDefinition;
use Aitumalow\Http\Requests\StoreWorkflowRequest;
use Aitumalow\Mcp\HandlesWorkflowErrors;
use Aitumalow\Mcp\Schemas\WorkflowDraftSchema;
use Aitumalow\Services\WorkflowDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create_workflow')]
#[Title('Create Workflow')]
#[Description('Create an empty draft or supply nodes and edges to create a complete validated draft atomically. Uses the editor catalog and validation. Never publishes or executes. Returns the saved draft and its hash for review/editing; no application PHP changes are needed.')]
final class CreateWorkflowTool extends Tool
{
    use HandlesWorkflowErrors;

    public function __construct(private readonly WorkflowDraftService $drafts) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Workflow name.'),
            'description' => $schema->string()->nullable(),
            'settings' => $schema->object()->nullable()->description('Same workflow settings as the editor, including max_concurrent_runs.'),
            'folder_id' => $schema->integer()->nullable(),
            'tag_ids' => $schema->array()->items($schema->integer()),
            ...WorkflowDraftSchema::fields($schema, required: false),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request): array {
            $metadata = Validator::make($request->only(['name', 'description', 'settings', 'folder_id', 'tag_ids']),
                new StoreWorkflowRequest()->rules())->validate();
            $graph = $request->validate([
                'nodes' => ['present_with:edges', 'array', 'list', 'max:250'],
                'edges' => ['present_with:nodes', 'array', 'list', 'max:1000'],
            ]);
            $definition = array_key_exists('nodes', $graph)
                ? new WorkflowDraftDefinition($graph['nodes'], $graph['edges']) : null;
            $draft = $this->drafts->create([...$metadata, 'created_via' => 'api'], $definition);

            return ['workflow' => $draft['workflow'], 'draft' => $draft];
        });
    }
}
