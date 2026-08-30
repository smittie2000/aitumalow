<?php

namespace Aitumalow\Tests;

use Aitumalow\AitumalowServiceProvider;
use Aitumalow\Http\EditorApiRoutes;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Testing\WorkflowTestHarness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Workflow\Providers\WorkflowServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->app->make(WorkflowTestHarness::class)->fake();
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
        return $this->app->make(WorkflowTestHarness::class)->drain($run);
    }
}
