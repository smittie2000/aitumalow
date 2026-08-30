<?php

declare(strict_types=1);

namespace Aitumalow\Http\Controllers;

use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\Models\Workflow;
use Aitumalow\Registry\ReferenceProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class WorkflowReferenceController extends Controller
{
    public function __construct(
        private readonly ReferenceProviderRegistry $providers,
        private readonly ExecutionScopeResolver $scopeResolver,
    ) {}

    public function index(Workflow $workflow, string $source): JsonResponse
    {
        abort_unless($this->providers->has($source), 404, "Reference source [{$source}] is not registered.");

        return response()->json([
            'data' => $this->providers->options(
                $source,
                $this->scopeResolver->resolve($workflow),
            ),
        ]);
    }
}
