<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\DTOs\WorkflowDraftDefinition;
use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Exceptions\WorkflowValidationException;
use Aitumalow\Services\WorkflowDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('save_workflow_draft')]
#[Title('Save Workflow Draft')]
#[Description('Atomically replace the complete mutable workflow graph returned by get_workflow_draft. Every capability and config is validated before commit. The active revision is never published, replaced, or activated by this tool.')]
final class SaveWorkflowDraftTool extends Tool
{
    public function __construct(
        private readonly WorkflowDraftService $drafts,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('Workflow ID.'),
            'expected_draft_hash' => $schema->string()->required()->description('Exact draft_hash returned by get_workflow_draft.'),
            'nodes' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required()->description('Document-local lowercase alias used by edges.'),
                'capability' => $schema->string()->required()->description('Exact stable key from show_workflow_node.'),
                'name' => $schema->string()->description('Optional instance name.'),
                'config' => $schema->object()->description('Configuration matching the registered capability schema.'),
            ]))->required()->description('Complete replacement node list.'),
            'edges' => $schema->array()->items($schema->object([
                'from' => $schema->string()->required()->description('Source node alias.'),
                'to' => $schema->string()->required()->description('Target node alias.'),
                'output' => $schema->string()->default('main')->description('Registered source output port.'),
                'input' => $schema->string()->default('main')->description('Registered target input port.'),
            ]))->required()->description('Complete replacement edge list.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'workflow_id' => ['required', 'integer'],
            'expected_draft_hash' => ['required', 'string', 'size:64'],
            'nodes' => ['present', 'array', 'max:250'],
            'edges' => ['present', 'array', 'max:1000'],
        ]);

        try {
            $draft = new WorkflowDraftDefinition(
                nodes: $validated['nodes'],
                edges: $validated['edges'],
            );

            return Response::structured([
                'draft' => $this->drafts->replace(
                    $validated['workflow_id'],
                    $draft,
                    $validated['expected_draft_hash'],
                ),
            ]);
        } catch (WorkflowDraftConflictException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        } catch (WorkflowValidationException $exception) {
            return Response::error('Workflow draft validation failed: '.implode(' ', $exception->errors));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        }
    }
}
