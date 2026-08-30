<?php

use Aitumalow\DTOs\RunCommand;
use Aitumalow\Enums\CommandStatus;
use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Services\WorkflowService;
use Aitumalow\Testing\WorkflowTestHarness;

it('waits on and resumes through an idempotent Durable update command', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
    ]);

    $waitNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'core.wait_resume',
        'name' => 'Wait for Approval',
        'config' => [],
    ]);

    $afterWait = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'After Approval',
        'config' => ['fields' => ['approved' => true]],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waitNode->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $waitNode->id,
        'source_port' => 'resume',
        'target_node_id' => $afterWait->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [['request_id' => 42]]));

    expect($run->status)->toBe(RunStatus::Waiting)
        ->and($run->nodeRuns->firstWhere('node_id', $waitNode->id))->toBeNull();

    $command = $service->command($run, new RunCommand(
        name: 'resume',
        idempotencyKey: 'approval:request:42',
        payload: ['approved_by' => 'lead:42'],
    ));
    expect($command->status)->toBe(CommandStatus::Accepted);
    $run = $this->drainDurableRun($run);
    $command = $service->synchronizeCommand($command);

    expect($command->error_message)->toBeNull()
        ->and($command->status)->toBe(CommandStatus::Completed)
        ->and($run->status)->toBe(RunStatus::Completed)
        ->and($run->nodeRuns->firstWhere('node_id', $waitNode->id)?->output)
        ->toBe(['resume' => [['approved_by' => 'lead:42']]])
        ->and($run->nodeRuns->firstWhere('node_id', $afterWait->id))->not->toBeNull();
});

it('routes declared human outcomes without a host-owned step engine', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $waitNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'core.wait_resume',
        'name' => 'Approval',
        'config' => [
            'commands' => [
                ['key' => 'approve', 'label' => 'Approve'],
                ['key' => 'reject', 'label' => 'Reject'],
            ],
        ],
    ]);
    $approved = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'config' => ['fields' => ['outcome' => 'approved']],
    ]);
    $rejected = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'config' => ['fields' => ['outcome' => 'rejected']],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waitNode->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $waitNode->id,
        'source_port' => 'approve',
        'target_node_id' => $approved->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $waitNode->id,
        'source_port' => 'reject',
        'target_node_id' => $rejected->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow->fresh(), [[]]));

    $request = new RunCommand(
        name: 'approve',
        idempotencyKey: 'deal-workstream:42:approval:1',
        payload: ['approved_by' => 'user:7'],
        expectedState: null,
    );
    $command = $service->command($run, $request);
    $duplicate = $service->command($run, $request);
    $run = $this->drainDurableRun($run);
    $command = $service->synchronizeCommand($command);

    expect($duplicate->is($command))->toBeTrue()
        ->and($run->commands()->count())->toBe(1)
        ->and($command->status)->toBe(CommandStatus::Completed)
        ->and($run->status)->toBe(RunStatus::Completed)
        ->and($run->nodeRuns->firstWhere('node_id', $waitNode->id)?->output)
        ->toBe(['approve' => [['approved_by' => 'user:7']]])
        ->and($run->nodeRuns->firstWhere('node_id', $approved->id))->not->toBeNull()
        ->and($run->nodeRuns->firstWhere('node_id', $rejected->id))->toBeNull();
});

it('can wait behind the wrapper until a command business effect is applied', function () {
    app(WorkflowTestHarness::class)->fake();

    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $waitNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'core.wait_resume',
        'config' => ['commands' => [['key' => 'approve', 'label' => 'Approve']]],
    ]);
    $afterWait = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'config' => ['fields' => ['approved' => true]],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waitNode->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $waitNode->id,
        'source_port' => 'approve',
        'target_node_id' => $afterWait->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow->fresh(), [[]]));
    $command = $service->commandAndWait($run, new RunCommand(
        name: 'approve',
        idempotencyKey: 'approval:wait:1',
    ));

    expect($command->status)->toBe(CommandStatus::Completed)
        ->and($run->nodeRuns()->where('node_id', $afterWait->id)->exists())->toBeTrue();
});

it('can cancel a waiting run via API', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Start']);
    $waitNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'core.wait_resume',
        'name' => 'Wait',
        'config' => [],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waitNode->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [['data' => 1]]));

    expect($run->status)->toBe(RunStatus::Waiting);

    $this->postJson("/workflow-engine/runs/{$run->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($run->fresh()->status)->toBe(RunStatus::Cancelled);
});

it('projects a stable state key and can re-enter it through a durable cycle', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $waitNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'core.wait_resume',
        'name' => 'Review',
        'config' => [
            'state_key' => 'review',
            'metadata' => ['lifecycle' => 'active'],
            'commands' => [[
                'key' => 'retry',
                'label' => 'Retry',
                'target_state' => 'review',
                'metadata' => ['ability' => 'requests.retry'],
            ]],
        ],
    ]);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Record retry',
        'config' => ['fields' => ['retried' => true]],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waitNode->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $waitNode->id,
        'source_port' => 'retry',
        'target_node_id' => $action->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $action->id,
        'target_node_id' => $waitNode->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $stateGraph = $workflow->fresh()->activeRevision->stateGraph();
    $run = $this->drainDurableRun($service->run($workflow->fresh(), [[]]));

    expect($stateGraph->state('review')->metadata)->toBe(['lifecycle' => 'active'])
        ->and($stateGraph->transitionsFrom('review'))->toHaveCount(1)
        ->and($stateGraph->transitionsFrom('review')[0]->targetState)->toBe('review')
        ->and($stateGraph->transitionsFrom('review')[0]->metadata)->toBe(['ability' => 'requests.retry'])
        ->and($run->waiting_state)->toBe('review');

    $service->command($run, new RunCommand(
        name: 'retry',
        idempotencyKey: 'review:retry:1',
        payload: ['reason' => 'new evidence'],
        expectedState: 'review',
    ));
    $run = $this->drainDurableRun($run);

    expect($run->status)->toBe(RunStatus::Waiting)
        ->and($run->waiting_node_id)->toBe($waitNode->id)
        ->and($run->waiting_state)->toBe('review')
        ->and($run->nodeRuns->firstWhere('node_id', $action->id))->not->toBeNull();
});
