<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Exceptions\WorkflowDraftConflictException;
use Aitumalow\Http\Resources\WorkflowRunResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class WorkflowRunController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function index(Request $request, Workflow $workflow): AnonymousResourceCollection
    {
        $runs = $workflow->runs()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return WorkflowRunResource::collection($runs);
    }

    public function show(WorkflowRun $run): WorkflowRunResource
    {
        $run->load(['nodeRuns', 'commands']);

        return new WorkflowRunResource($run);
    }

    public function cancel(WorkflowRun $run): WorkflowRunResource
    {
        $run = $this->service->cancel($run);

        return new WorkflowRunResource($run);
    }

    public function resume(Request $request, WorkflowRun $run): WorkflowRunResource
    {
        $request->validate([
            'payload' => ['nullable', 'array'],
            'payload.*' => ['array'],
        ]);

        $run = $this->service->resume(
            run: $run,
            payload: $request->input('payload', []),
        );

        return new WorkflowRunResource($run->load('nodeRuns'));
    }

    public function replay(WorkflowRun $run): WorkflowRunResource
    {
        $newRun = $this->service->replay($run);

        return new WorkflowRunResource($newRun->load('nodeRuns'));
    }

    public function testNode(Request $request, Workflow $workflow): WorkflowRunResource|JsonResponse
    {
        $request->validate([
            'node_id' => ['required', 'integer'],
            'payload' => ['nullable', 'array', 'list'],
            'payload.*' => ['array'],
            'expected_graph_hash' => ['sometimes', 'string', 'size:64'],
        ]);

        try {
            $run = $this->service->testNode(
                $workflow,
                $request->integer('node_id'),
                $request->input('payload', []),
                $request->input('expected_graph_hash'),
            );
        } catch (WorkflowDraftConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return new WorkflowRunResource($run->load('nodeRuns'));
    }
}
