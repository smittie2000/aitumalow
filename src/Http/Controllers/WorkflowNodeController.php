<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Requests\StoreNodeRequest;
use Aitumalow\Http\Requests\UpdateNodeRequest;
use Aitumalow\Http\Resources\WorkflowNodeResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Services\WorkflowService;
use Aitumalow\Services\WorkflowVariableService;
use Aitumalow\Support\ConfiguredModels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WorkflowNodeController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function store(StoreNodeRequest $request, Workflow $workflow): WorkflowNodeResource
    {
        $node = $this->service->addNode(
            workflow: $workflow,
            nodeKey: $request->validated('node_key'),
            config: $request->validated('config', []),
            name: $request->validated('name'),
            positionX: $request->integer('position_x'),
            positionY: $request->integer('position_y'),
        );

        return new WorkflowNodeResource($node);
    }

    public function update(UpdateNodeRequest $request, Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $node = $this->service->updateNode($node, $request->validated());

        return new WorkflowNodeResource($node);
    }

    public function destroy(Workflow $workflow, WorkflowNode $node): JsonResponse
    {
        $this->service->removeNode($node->id);

        return response()->json(['message' => 'Node deleted.'], 200);
    }

    public function position(Request $request, Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $request->validate([
            'position_x' => ['required', 'integer'],
            'position_y' => ['required', 'integer'],
        ]);

        $node = $this->service->updateNode($node, [
            'position_x' => $request->integer('position_x'),
            'position_y' => $request->integer('position_y'),
        ]);

        return new WorkflowNodeResource($node->fresh());
    }

    public function availableVariables(Workflow $workflow, WorkflowNode $node): JsonResponse
    {
        return response()->json(app(WorkflowVariableService::class)->available($workflow, $node));
    }

    public function pin(Request $request, Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $request->validate([
            'source' => ['required', 'string', 'in:run,manual'],
            'node_run_id' => ['required_if:source,run', 'integer'],
            'input' => ['nullable', 'array'],
            'output' => ['nullable', 'array'],
        ]);

        if ($request->input('source') === 'run') {
            $nodeRun = ConfiguredModels::nodeRun()::findOrFail($request->integer('node_run_id'));

            abort_unless($nodeRun->node_id === $node->id, 422, 'Node run does not belong to this node.');

            $pinnedData = [
                'input' => $nodeRun->input,
                'output' => $nodeRun->output,
                'source_run_id' => $nodeRun->workflow_run_id,
            ];
        } else {
            $pinnedData = array_filter([
                'input' => $request->input('input'),
                'output' => $request->input('output'),
            ], fn ($v) => $v !== null);
        }

        $node = $this->service->updateNode($node, ['pinned_data' => $pinnedData]);

        return new WorkflowNodeResource($node->fresh());
    }

    public function unpin(Workflow $workflow, WorkflowNode $node): WorkflowNodeResource
    {
        $node = $this->service->updateNode($node, ['pinned_data' => null]);

        return new WorkflowNodeResource($node->fresh());
    }
}
