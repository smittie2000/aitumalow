<?php

namespace Aitumalow\Tests;

use Aitumalow\AitumalowServiceProvider;
use Aitumalow\Http\EditorApiRoutes;
use Aitumalow\Models\WorkflowRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Workflow\Providers\WorkflowServiceProvider;
use Workflow\V2\Enums\TaskStatus;
use Workflow\V2\Enums\TaskType;
use Workflow\V2\Jobs\RunActivityTask;
use Workflow\V2\Jobs\RunTimerTask;
use Workflow\V2\Jobs\RunWorkflowTask;
use Workflow\V2\Models\WorkflowTask;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    protected function getPackageProviders($app): array
    {
        return [
            WorkflowServiceProvider::class,
            AitumalowServiceProvider::class,
            McpServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('queue.default', 'database');
    }

    protected function defineRoutes($router): void
    {
        $router->prefix('workflow-engine')
            ->middleware('api')
            ->name('aitumalow.')
            ->group(fn () => EditorApiRoutes::register());
    }

    protected function drainDurableRun(WorkflowRun|string $run): WorkflowRun
    {
        $durableRunId = $run instanceof WorkflowRun ? $run->durable_run_id : $run;
        if ($durableRunId === null) {
            throw new \RuntimeException('A Durable run id is required.');
        }

        $originalNow = Carbon::getTestNow();

        try {
            for ($step = 0; $step < 1000; $step++) {
                $task = WorkflowTask::query()
                    ->where('workflow_run_id', $durableRunId)
                    ->where('status', TaskStatus::Ready->value)
                    ->orderBy('created_at')
                    ->first();

                if ($task === null) {
                    break;
                }

                $availableAt = $task->getAttribute('available_at');
                if ($availableAt instanceof Carbon && $availableAt->isFuture()) {
                    Carbon::setTestNow($availableAt);
                }

                $taskType = $task->getAttribute('task_type');
                if (! $taskType instanceof TaskType) {
                    throw new \RuntimeException('Durable task has no typed task type.');
                }

                $job = match ($taskType) {
                    TaskType::Workflow => new RunWorkflowTask($task->id),
                    TaskType::Activity => new RunActivityTask($task->id),
                    TaskType::Timer => new RunTimerTask($task->id),
                };

                $this->app->call([$job, 'handle']);
            }
        } finally {
            Carbon::setTestNow($originalNow);
        }

        $projection = $run instanceof WorkflowRun
            ? $run->fresh('nodeRuns')
            : WorkflowRun::query()->where('durable_run_id', $durableRunId)->firstOrFail()->load('nodeRuns');

        return $projection->synchronizeDurableState()->load('nodeRuns');
    }
}
