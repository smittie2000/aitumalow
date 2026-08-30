<?php

use Aitumalow\Engine\GraphValidator;
use Aitumalow\Exceptions\WorkflowValidationException;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;

beforeEach(function () {
    $this->validator = app(GraphValidator::class);
});

it('validates a valid linear workflow', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $action = WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'core.set_fields',
        'config' => ['fields' => ['status' => 'done']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    expect(fn () => $this->validator->validate($workflow))->not->toThrow(WorkflowValidationException::class);
});

it('fails when there is no trigger node', function () {
    $workflow = Workflow::factory()->create();

    WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'action',
        'config' => ['fields' => ['x' => 'y']],
    ]);

    $errors = $this->validator->errors($workflow);

    expect($errors)->toContain('Workflow must have at least one trigger node.');
});

it('fails when there are multiple trigger nodes', function () {
    $workflow = Workflow::factory()->create();

    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger1']);
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger2']);

    $errors = $this->validator->errors($workflow);

    expect($errors)->toContain('Workflow must have exactly one trigger node, found 2.');
});

it('fails when a node uses an unregistered key', function () {
    $workflow = Workflow::factory()->create();

    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'node_key' => 'completely_fake_node',
        'name' => 'fake',
    ]);

    $errors = $this->validator->errors($workflow);

    expect(count($errors))->toBeGreaterThanOrEqual(1)
        ->and(collect($errors)->first(fn ($e) => str_contains($e, 'unregistered key')))->not->toBeNull();
});

it('rejects cycles that can spin without a durable boundary', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $nodeA = WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'A',
        'config' => ['fields' => ['x' => 'y']],
    ]);
    $nodeB = WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'B',
        'config' => ['fields' => ['x' => 'y']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $nodeA->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $nodeA->id,
        'target_node_id' => $nodeB->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $nodeB->id,
        'target_node_id' => $nodeA->id,
    ]);

    $errors = $this->validator->errors($workflow);

    expect(collect($errors)->first(fn ($e) => str_contains($e, 'cycle')))->not->toBeNull();
});

it('allows state-machine cycles that cross a durable wait boundary', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $waiting = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'node_key' => 'core.wait_resume',
        'name' => 'Review',
        'config' => [
            'state_key' => 'review',
            'commands' => [['key' => 'retry', 'label' => 'Retry']],
        ],
    ]);
    $action = WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Retry action',
        'config' => ['fields' => ['retried' => true]],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waiting->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $waiting->id,
        'source_port' => 'retry',
        'target_node_id' => $action->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $action->id,
        'target_node_id' => $waiting->id,
    ]);

    expect(fn () => $this->validator->validate($workflow))->not->toThrow(WorkflowValidationException::class);
});

it('detects unreachable nodes', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $connected = WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'connected',
        'config' => ['fields' => ['x' => 'y']],
    ]);
    $orphan = WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'orphan',
        'config' => ['fields' => ['x' => 'y']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $connected->id,
    ]);

    $errors = $this->validator->errors($workflow);

    expect(collect($errors)->first(fn ($e) => str_contains($e, 'unreachable')))->not->toBeNull();
});

it('returns empty errors for valid workflow', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $action = WorkflowNode::factory()->action('core.set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'action',
        'config' => ['fields' => ['status' => 'done']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    expect($this->validator->errors($workflow))->toBeEmpty();
});
