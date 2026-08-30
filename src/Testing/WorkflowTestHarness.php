<?php

declare(strict_types=1);

namespace Aitumalow\Testing;

use Aitumalow\Models\WorkflowRun;
use Illuminate\Support\Carbon;
use LogicException;
use RuntimeException;
use Workflow\V2\Enums\TaskStatus;
use Workflow\V2\Models\WorkflowTask;
use Workflow\V2\WorkflowStub;

/**
 * Public Aitumalow testing seam for executing workflows without a worker.
 *
 * Durable Workflow remains an implementation detail of this package. Host test
 * suites should use this harness instead of importing engine jobs or models.
 */
final class WorkflowTestHarness
{
    public function fake(): self
    {
        $this->ensureTestingEnvironment();

        WorkflowStub::fake();

        return $this;
    }

    public function drain(WorkflowRun|string $run, int $taskLimit = 1000): WorkflowRun
    {
        $this->ensureTestingEnvironment();

        if (! WorkflowStub::faked()) {
            throw new LogicException('Call WorkflowTestHarness::fake() before draining workflows.');
        }

        $projection = $run instanceof WorkflowRun
            ? $run
            : WorkflowRun::query()->findOrFail($run);
        $durableRunId = $projection->durable_run_id;

        if ($durableRunId === null) {
            throw new RuntimeException('The Aitumalow run has not been started.');
        }

        $originalNow = Carbon::getTestNow();

        try {
            for ($step = 0; $step < $taskLimit; $step++) {
                $task = WorkflowTask::query()
                    ->where('workflow_run_id', $durableRunId)
                    ->where('status', TaskStatus::Ready->value)
                    ->orderBy('available_at')
                    ->orderBy('created_at')
                    ->first();

                if ($task === null) {
                    break;
                }

                $availableAt = $task->getAttribute('available_at');
                if ($availableAt instanceof Carbon && $availableAt->isFuture()) {
                    Carbon::setTestNow($availableAt);
                }

                if (WorkflowStub::runReadyTasks() === 0) {
                    throw new RuntimeException("Aitumalow could not drain task {$task->id}.");
                }
            }
        } finally {
            Carbon::setTestNow($originalNow);
        }

        return $projection->fresh('nodeRuns')->synchronizeDurableState()->load('nodeRuns');
    }

    private function ensureTestingEnvironment(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('The Aitumalow workflow test harness is only available in the testing environment.');
        }
    }
}
