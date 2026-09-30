<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Exceptions\WorkflowValidationException;
use Aitumalow\Http\Requests\StoreWorkflowRequest;
use Aitumalow\Http\Requests\UpdateWorkflowRequest;
use Aitumalow\Http\Resources\WorkflowResource;
use Aitumalow\Http\Resources\WorkflowRunResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Services\WorkflowListingService;
use Aitumalow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workflows = app(WorkflowListingService::class)->query([
            ...$request->only(['search', 'sort', 'direction', 'folder_id', 'tag', 'tag_id']),
            'active_only' => $request->boolean('active_only'), 'uncategorized' => $request->boolean('uncategorized'),
        ])->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return WorkflowResource::collection($workflows);
    }

    public function store(StoreWorkflowRequest $request): WorkflowResource
    {
        $workflow = $this->service->create($request->validated());

        return new WorkflowResource($workflow->load(['tags', 'folder', 'activeRevision']));
    }

    public function show(Workflow $workflow): WorkflowResource
    {
        $workflow->load(['nodes', 'edges', 'tags', 'folder', 'activeRevision']);

        return new WorkflowResource($workflow);
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow): WorkflowResource
    {
        $workflow = $this->service->update($workflow, $request->validated());

        return new WorkflowResource($workflow->load(['tags', 'folder', 'activeRevision']));
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $this->service->delete($workflow);

        return response()->json(['message' => 'Workflow deleted.'], 200);
    }

    public function activate(Workflow $workflow): WorkflowResource|JsonResponse
    {
        try {
            $workflow = $this->service->activate($workflow);
        } catch (WorkflowValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ], 422);
        }

        return new WorkflowResource($workflow->load('activeRevision'));
    }

    public function deactivate(Workflow $workflow): WorkflowResource
    {
        $workflow = $this->service->deactivate($workflow);

        return new WorkflowResource($workflow);
    }

    public function run(Request $request, Workflow $workflow): WorkflowRunResource|JsonResponse
    {
        $payload = $request->input('payload', []);

        $run = $this->service->run($workflow, $payload);

        return new WorkflowRunResource($run->load('nodeRuns'))
            ->response()
            ->setStatusCode(202);
    }

    public function duplicate(Workflow $workflow): WorkflowResource
    {
        $copy = $this->service->duplicate($workflow);

        return new WorkflowResource($copy);
    }

    public function validateWorkflow(Workflow $workflow): JsonResponse
    {
        $errors = $this->service->validate($workflow);

        if (empty($errors)) {
            return response()->json(['valid' => true, 'errors' => []]);
        }

        return response()->json(['valid' => false, 'errors' => $errors], 422);
    }
}
