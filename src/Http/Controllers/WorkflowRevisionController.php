<?php

declare(strict_types=1);

namespace Aitumalow\Http\Controllers;

use Aitumalow\Http\Resources\WorkflowResource;
use Aitumalow\Http\Resources\WorkflowRevisionResource;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowRevision;
use Aitumalow\Runtime\WorkflowSnapshot;
use Aitumalow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class WorkflowRevisionController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
        private readonly WorkflowSnapshot $snapshots,
    ) {}

    public function index(Workflow $workflow): AnonymousResourceCollection
    {
        return WorkflowRevisionResource::collection(
            $workflow->revisions()->orderByDesc('version')->get(),
        );
    }

    public function restoreDraft(
        Workflow $workflow,
        WorkflowRevision $revision,
    ): WorkflowResource {
        return new WorkflowResource(
            $this->service->restoreDraft($workflow, $revision),
        );
    }

    public function compareDraft(
        Workflow $workflow,
        WorkflowRevision $revision,
    ): JsonResponse {
        $revisionDefinition = $this->snapshots->fromRevision($revision);

        return response()->json([
            'data' => [
                'revision' => new WorkflowRevisionResource($revision)->resolve(),
                'revision_definition' => $revisionDefinition,
                'draft_definition' => $this->snapshots->captureDraft($workflow),
            ],
        ]);
    }
}
