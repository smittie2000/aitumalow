<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Requests\StoreEdgeRequest;
use Aitumalow\Http\Resources\WorkflowEdgeResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class WorkflowEdgeController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function store(StoreEdgeRequest $request, Workflow $workflow): WorkflowEdgeResource
    {
        $edge = $this->service->connect(
            source: $request->validated('source_node_id'),
            target: $request->validated('target_node_id'),
            sourcePort: $request->validated('source_port', 'main'),
            targetPort: $request->validated('target_port', 'main'),
        );

        return new WorkflowEdgeResource($edge);
    }

    public function destroy(Workflow $workflow, WorkflowEdge $edge): JsonResponse
    {
        $this->service->removeEdge($edge->id);

        return response()->json(['message' => 'Edge deleted.'], 200);
    }
}
