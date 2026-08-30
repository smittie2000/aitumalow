<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\Enums\NodeRunStatus;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Events\WorkflowCompleted as AitumalowWorkflowCompleted;
use Aitumalow\Events\WorkflowFailed as AitumalowWorkflowFailed;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Models\WorkflowRun;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Workflow\V2\Events\ActivityFailed;
use Workflow\V2\Events\WorkflowCompleted;
use Workflow\V2\Events\WorkflowFailed;
use Workflow\V2\WorkflowStub;

final class RuntimeProjectionListener
{
    public static function register(): void
    {
        Event::listen(WorkflowCompleted::class, self::completed(...));
        Event::listen(WorkflowFailed::class, self::failed(...));
        Event::listen(ActivityFailed::class, self::activityFailed(...));
    }

    private static function completed(WorkflowCompleted $event): void
    {
        $run = WorkflowRun::query()->where('durable_run_id', $event->runId)->first();
        if ($run === null) {
            return;
        }

        $output = WorkflowStub::loadRun($event->runId)->output();
        $context = is_array($output) ? $output : [];
        $run->update([
            'status' => RunStatus::Completed,
            'context' => $context,
            'finished_at' => now(),
        ]);
        event(new AitumalowWorkflowCompleted($run, $context));
    }

    private static function failed(WorkflowFailed $event): void
    {
        $run = WorkflowRun::query()->where('durable_run_id', $event->runId)->first();
        if ($run === null) {
            return;
        }

        $run->update([
            'status' => RunStatus::Failed,
            'error_message' => $event->message,
            'finished_at' => now(),
        ]);
        event(new AitumalowWorkflowFailed(
            $run,
            new RuntimeException($event->message),
            $run->context ?? [],
        ));
    }

    private static function activityFailed(ActivityFailed $event): void
    {
        WorkflowNodeRun::query()
            ->where('durable_activity_id', $event->activityExecutionId)
            ->update([
                'status' => NodeRunStatus::Failed,
                'error_message' => $event->message,
                'attempts' => $event->attemptNumber,
            ]);
    }
}
