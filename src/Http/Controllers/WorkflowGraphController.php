<?php

declare(strict_types=1);

namespace Aitumalow\Http\Controllers;

use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Http\Resources\WorkflowResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Services\WorkflowGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WorkflowGraphController extends Controller
{
    public function __construct(private readonly WorkflowGraphService $graphs) {}

    public function show(Workflow $workflow): JsonResponse
    {
        return $this->response($this->graphs->get($workflow));
    }

    public function store(Request $request, Workflow $workflow): JsonResponse
    {
        try {
            return $this->response($this->graphs->edit($workflow, $request->all()));
        } catch (WorkflowDraftConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }

    /** @param array<string, mixed> $state */
    private function response(array $state): JsonResponse
    {
        return response()->json(['data' => [
            ...$state,
            'workflow' => new WorkflowResource($state['workflow']),
        ]]);
    }
}
