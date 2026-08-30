<?php

use Aitumalow\Enums\NodeRunStatus;
use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Services\WorkflowService;

// ── Helper ──────────────────────────────────────────────────────

/** @return array{Workflow, WorkflowNode, WorkflowNode} */
function createSimpleWorkflow(): array
{
    $workflow = Workflow::factory()->active()->create();

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

    return [$workflow, $trigger, $setFields];
}

// ── API: Pin from run ───────────────────────────────────────────

it('pins test data from a previous node run', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->create(['workflow_id' => $workflow->id]);

    $run = WorkflowRun::factory()->create(['workflow_id' => $workflow->id]);
    $nodeRun = WorkflowNodeRun::factory()->create([
        'workflow_run_id' => $run->id,
        'node_id' => $node->id,
        'input' => [['name' => 'Alice']],
        'output' => ['main' => [['name' => 'Alice', 'processed' => true]]],
    ]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}/pin", [
        'source' => 'run',
        'node_run_id' => $nodeRun->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.pinned_data.input', [['name' => 'Alice']])
        ->assertJsonPath('data.pinned_data.output.main', [['name' => 'Alice', 'processed' => true]])
        ->assertJsonPath('data.pinned_data.source_run_id', $run->id);
});

// ── API: Pin manual data ────────────────────────────────────────

it('pins manually provided test data', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->create(['workflow_id' => $workflow->id]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}/pin", [
        'source' => 'manual',
        'input' => [['foo' => 'bar']],
        'output' => ['main' => [['result' => 42]]],
    ])
        ->assertOk()
        ->assertJsonPath('data.pinned_data.input', [['foo' => 'bar']])
        ->assertJsonPath('data.pinned_data.output.main', [['result' => 42]]);
});

// ── API: Unpin ──────────────────────────────────────────────────

it('unpins test data from a node', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()
        ->withPinnedOutput(['main' => [['x' => 1]]])
        ->create(['workflow_id' => $workflow->id]);

    expect($node->pinned_data)->not->toBeNull();

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}/pin")
        ->assertOk()
        ->assertJsonPath('data.pinned_data', null);

    expect($node->fresh()->pinned_data)->toBeNull();
});

// ── API: Validation ─────────────────────────────────────────────

it('rejects pin when node_run belongs to a different node', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->create(['workflow_id' => $workflow->id]);
    $otherNode = WorkflowNode::factory()->create(['workflow_id' => $workflow->id]);

    $run = WorkflowRun::factory()->create(['workflow_id' => $workflow->id]);
    $nodeRun = WorkflowNodeRun::factory()->create([
        'workflow_run_id' => $run->id,
        'node_id' => $otherNode->id,
    ]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}/pin", [
        'source' => 'run',
        'node_run_id' => $nodeRun->id,
    ])->assertStatus(422);
});

// ── Execution: Pinned output skips execution ────────────────────

it('uses pinned output and skips execution in test mode', function () {
    [$workflow, $trigger, $setFields] = createSimpleWorkflow();

    $pinnedOutput = ['main' => [['pinned' => true, 'custom' => 'value']]];
    $setFields->update(['pinned_data' => ['output' => $pinnedOutput]]);

    $service = app(WorkflowService::class);
    $run = $this->drainDurableRun(
        $service->testNode($workflow, $setFields->id, [['name' => 'Alice']]),
    );

    expect($run->status)->toBe(RunStatus::Completed);

    $nodeRun = $run->nodeRuns->firstWhere('node_id', $setFields->id);
    expect($nodeRun->status)->toBe(NodeRunStatus::Completed)
        ->and($nodeRun->output)->toBe($pinnedOutput)
        ->and($nodeRun->duration_ms)->toBe(0);
});

// ── Execution: Pinned input replaces computed input ─────────────

it('uses pinned input but still executes the node in test mode', function () {
    [$workflow, $trigger, $setFields] = createSimpleWorkflow();

    $pinnedInput = [['name' => 'PinnedUser', 'extra' => 'data']];
    $setFields->update(['pinned_data' => ['input' => $pinnedInput]]);

    $service = app(WorkflowService::class);
    $run = $this->drainDurableRun(
        $service->testNode($workflow, $setFields->id, [['name' => 'Alice']]),
    );

    expect($run->status)->toBe(RunStatus::Completed);

    $nodeRun = $run->nodeRuns->firstWhere('node_id', $setFields->id);
    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);
    // The node received pinned input, so its input should be the pinned items
    expect($nodeRun->input)->toBe($pinnedInput);
    // set_fields with keep_existing=true merges fields, so output should contain pinned input fields + processed
    expect($nodeRun->output['main'][0])->toHaveKey('name', 'PinnedUser');
    expect($nodeRun->output['main'][0])->toHaveKey('processed', true);
});

// ── Execution: Pinned data ignored in normal runs ───────────────

it('ignores pinned data during normal workflow execution', function () {
    [$workflow, $trigger, $setFields] = createSimpleWorkflow();

    $pinnedOutput = ['main' => [['pinned' => true]]];
    $setFields->update(['pinned_data' => ['output' => $pinnedOutput]]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $run = $this->drainDurableRun($service->run($workflow, [['name' => 'Alice']]));

    expect($run->status)->toBe(RunStatus::Completed);

    $nodeRun = $run->nodeRuns->firstWhere('node_id', $setFields->id);
    // Normal run should NOT use pinned output
    expect($nodeRun->output)->not->toBe($pinnedOutput);
    expect($nodeRun->output['main'][0])->toHaveKey('processed', true);
});

// ── Resource: pinned_data in response ───────────────────────────

it('includes pinned_data in workflow node response', function () {
    $workflow = Workflow::factory()->create();
    WorkflowNode::factory()
        ->trigger()
        ->withPinnedOutput(['main' => [['test' => true]]])
        ->create(['workflow_id' => $workflow->id]);

    $this->getJson("/workflow-engine/workflows/{$workflow->id}")
        ->assertOk()
        ->assertJsonPath('data.nodes.0.pinned_data.output.main', [['test' => true]]);
});
