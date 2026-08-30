<?php

declare(strict_types=1);

namespace Aitumalow\Mcp\Tools;

use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\Models\Workflow;
use Aitumalow\Registry\ReferenceProviderRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workflow_references')]
#[Title('List Workflow References')]
#[Description('List the safe opaque values a workflow may use for a registered reference source found in a workflow node schema.')]
#[IsReadOnly]
final class ListWorkflowReferencesTool extends Tool
{
    public function __construct(
        private readonly ReferenceProviderRegistry $providers,
        private readonly ExecutionScopeResolver $scopeResolver,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('Workflow ID used to resolve the host execution scope.'),
            'source' => $schema->string()->required()->description('Exact reference source from a workflow node configuration schema.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $source = $request->string('source')->toString();

        if (! $this->providers->has($source)) {
            return Response::error("Reference source [{$source}] is not registered.");
        }

        $workflow = Workflow::findOrFail($request->integer('workflow_id'));

        return Response::structured(['references' => $this->providers->options(
            $source,
            $this->scopeResolver->resolve($workflow),
        )]);
    }
}
