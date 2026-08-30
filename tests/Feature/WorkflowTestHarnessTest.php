<?php

declare(strict_types=1);

use Aitumalow\Facades\Workflow as WorkflowFacade;
use Aitumalow\Models\Workflow;
use Aitumalow\Testing\WorkflowTestHarness;

it('lets a host execute workflows without importing the underlying engine', function (): void {
    app(WorkflowTestHarness::class)->fake();

    $workflow = Workflow::factory()->create();
    WorkflowFacade::addNode($workflow, 'core.manual');
    WorkflowFacade::activate($workflow);

    $run = WorkflowFacade::run($workflow->fresh(), [['message' => 'wrapped']]);
    $completed = app(WorkflowTestHarness::class)->drain($run);

    expect($completed->status->value)->toBe('completed')
        ->and($completed->durable_run_id)->not->toBeNull();
});
