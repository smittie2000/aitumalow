<?php

use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Runtime\ExecuteCapabilityActivity;
use Aitumalow\Services\WorkflowService;
use Workflow\V2\Models\ActivityExecution;

it('runs a workflow end-to-end via service', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
    ]);

    $setFields = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Transform',
        'config' => ['fields' => ['processed' => true], 'keep_existing' => true],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $setFields->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [['name' => 'Alice']]));

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->nodeRuns)->toHaveCount(2)
        ->and($run->context)->not->toBeNull();

    $capabilityExecutions = ActivityExecution::query()
        ->where('workflow_run_id', $run->durable_run_id)
        ->where('activity_class', ExecuteCapabilityActivity::class)
        ->get();

    expect($capabilityExecutions)->toHaveCount(2)
        ->and($capabilityExecutions->every(
            static fn (ActivityExecution $execution): bool => data_get(
                $execution->getAttribute('activity_options'),
                'execution_mode',
            ) === 'local',
        ))->toBeTrue();
});

it('runs a branching workflow with condition', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Start']);

    $condition = WorkflowNode::factory()->condition()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Check',
        'config' => ['field' => 'active', 'operator' => 'equals', 'value' => true],
    ]);

    $trueNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Active Path',
        'config' => ['fields' => ['path' => 'true']],
    ]);

    $falseNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Inactive Path',
        'config' => ['fields' => ['path' => 'false']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $condition->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $condition->id,
        'source_port' => 'true',
        'target_node_id' => $trueNode->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $condition->id,
        'source_port' => 'false',
        'target_node_id' => $falseNode->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [['active' => true]]));

    expect($run->status)->toBe(RunStatus::Completed);
});

it('runs a workflow with loop node', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Start']);

    $loop = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'core.loop',
        'name' => 'Loop',
        'config' => ['source_field' => 'items'],
    ]);

    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Process Item',
        'config' => ['fields' => ['processed' => true]],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $loop->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $loop->id,
        'source_port' => 'loop_item',
        'target_node_id' => $action->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [['items' => ['a', 'b', 'c']]]));

    expect($run->status)->toBe(RunStatus::Completed);
});

it('rejects a workflow without an active published revision before creating durable history', function () {
    $workflow = Workflow::factory()->create();

    $service = app(WorkflowService::class);
    $service->run($workflow);
})->throws(LogicException::class);

it('cancels a running workflow', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Start']);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Set',
        'config' => ['fields' => ['x' => 1]],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [[]]));

    // Run is already completed, cancel should be a no-op
    $cancelled = $service->cancel($run);
    expect($cancelled->status)->toBe(RunStatus::Completed);
});

it('lists runs for a workflow via API', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Start']);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Set',
        'config' => ['fields' => ['x' => 1]],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $service->run($workflow, [['data' => 1]]);
    $service->run($workflow, [['data' => 2]]);

    $this->getJson("/workflow-engine/workflows/{$workflow->id}/runs")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows run details with node runs', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Start']);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Set',
        'config' => ['fields' => ['x' => 1]],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [['data' => 1]]));

    $this->getJson("/workflow-engine/runs/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonCount(2, 'data.node_runs');
});
