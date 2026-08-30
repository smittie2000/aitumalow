<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Services\WorkflowDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_workflow_draft')]
#[Title('Get Workflow Draft')]
#[Description('Get the complete mutable workflow graph with request-local node aliases and a draft hash. Edit this document and pass it to save_workflow_draft; the active revision is a separate immutable snapshot.')]
#[IsReadOnly]
final class GetWorkflowDraftTool extends Tool
{
    public function __construct(
        private readonly WorkflowDraftService $drafts,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('Workflow ID.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'draft' => $this->drafts->get($request->integer('workflow_id')),
        ]);
    }
}
