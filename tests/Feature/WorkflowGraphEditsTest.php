<?php

declare(strict_types=1);

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Services\WorkflowGraphService;
use Aitumalow\Services\WorkflowService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->workflow = Workflow::factory()->create();
    $this->graphs = app(WorkflowGraphService::class);
    $this->url = "/workflow-engine/workflows/{$this->workflow->id}";
});

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function graphEditRequest(Workflow $workflow, string $operation, array $data): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'expected_hash' => app(WorkflowGraphService::class)->get($workflow)['hash'],
        'operation' => $operation,
        'data' => $data,
    ];
}

/** @return array<string, mixed> */
function graphStep(string $key = 'core.set_fields'): array
{
    return ['node_key' => $key, 'name' => 'New step', 'config' => [], 'position_x' => 120, 'position_y' => 350];
}

it('atomically appends a draft step and retries the same request without duplicating it', function (): void {
    $trigger = app(WorkflowService::class)->addNode($this->workflow, 'core.manual');
    $request = graphEditRequest($this->workflow, 'add_node', [
        ...graphStep(), 'source' => ['node_id' => $trigger->id, 'port' => 'main'], 'input_port' => 'main',
    ]);
    $first = $this->postJson($this->url.'/graph-edits', $request)->assertOk()->json('data');
    $again = $this->postJson($this->url.'/graph-edits', $request)->assertOk()->json('data');

    expect($again['edit'])->toBe($first['edit'])
        ->and($again['hash'])->toBe($first['hash'])
        ->and($this->workflow->nodes()->count())->toBe(2)
        ->and($this->workflow->edges()->count())->toBe(1);

    $request['data']['name'] = 'Different request';
    $this->postJson($this->url.'/graph-edits', $request)->assertConflict();
});

it('inserts into one branch without replacing other identities or moving saved nodes', function (): void {
    $service = app(WorkflowService::class);
    $branch = $service->addNode($this->workflow, 'core.if_condition', ['field' => 'priority', 'operator' => 'equals', 'value' => 'high']);
    $target = $service->addNode($this->workflow, 'core.delay');
    $other = $service->addNode($this->workflow, 'core.delay');
    $edge = $service->connect($branch, $target, 'true');
    $otherEdge = $service->connect($branch, $other, 'false');
    $before = $this->graphs->get($this->workflow);
    $request = graphEditRequest($this->workflow, 'add_node', [
        ...graphStep('core.if_condition'), 'edge_id' => $edge->id, 'input_port' => 'main', 'output_port' => 'false',
    ]);
    $result = $this->postJson($this->url.'/graph-edits', $request)->assertOk()->json('data');
    $created = $result['edit']['created_node_id'];

    expect($edge->refresh()->target_node_id)->toBe($created)
        ->and($edge->source_port)->toBe('true')
        ->and($otherEdge->refresh()->target_node_id)->toBe($other->id)
        ->and($target->refresh()->position_x)->toBe(0)
        ->and($this->workflow->edges()->where('source_node_id', $created)->firstOrFail()->source_port)->toBe('false');

    $undo = $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'undo', ['edit_id' => $result['edit']['id']]))->assertOk()->json('data');
    expect($undo['hash'])->toBe($before['hash'])
        ->and($edge->refresh()->target_node_id)->toBe($target->id);
    $redo = $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'redo', ['edit_id' => $result['edit']['id']]))->assertOk()->json('data');
    expect($redo['hash'])->toBe($result['hash'])
        ->and($edge->refresh()->target_node_id)->toBe($created);
});

it('rolls back node creation and rewiring if an insertion port is invalid', function (): void {
    $service = app(WorkflowService::class);
    $source = $service->addNode($this->workflow, 'core.manual');
    $target = $service->addNode($this->workflow, 'core.delay');
    $edge = $service->connect($source, $target);
    $before = $this->graphs->get($this->workflow)['hash'];
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'add_node', [
        ...graphStep(), 'edge_id' => $edge->id, 'input_port' => 'main', 'output_port' => 'case_fake',
    ]))->assertUnprocessable();
    expect($this->graphs->get($this->workflow)['hash'])->toBe($before)
        ->and($this->workflow->graphEdits()->count())->toBe(0);
});

it('accepts incomplete draft settings but still rejects invalid values and publication', function (): void {
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'add_node', graphStep('core.switch')))->assertOk();
    $node = $this->workflow->nodes()->firstOrFail();
    $this->postJson($this->url.'/validate')->assertUnprocessable()->assertJsonPath('valid', false);
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'update_node', [
        'node_id' => $node->id, 'name' => 'Unsaved name', 'config' => ['cases' => 'not an array'],
    ]))->assertUnprocessable();
    expect($node->refresh()->name)->toBe('New step');
});

it('rejects stale writes after a legacy position change and rejects undo across newer edits', function (): void {
    $created = $this->graphs->edit($this->workflow, graphEditRequest($this->workflow, 'add_node', graphStep()));
    $nodeId = $created['edit']['created_node_id'];
    $stale = graphEditRequest($this->workflow, 'update_node', ['node_id' => $nodeId, 'name' => 'Overwrite']);
    $this->patchJson($this->url."/nodes/{$nodeId}/position", ['position_x' => 990, 'position_y' => 10])->assertOk();
    $this->postJson($this->url.'/graph-edits', $stale)->assertConflict();
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'undo', ['edit_id' => $created['edit']['id']]))->assertConflict();
});

it('returns the current graph when an old request is retried after undo', function (): void {
    $request = graphEditRequest($this->workflow, 'add_node', graphStep());
    $created = $this->graphs->edit($this->workflow, $request);
    $this->graphs->edit($this->workflow, graphEditRequest($this->workflow, 'undo', ['edit_id' => $created['edit']['id']]));
    $retried = $this->graphs->edit($this->workflow, $request);
    expect($retried['hash'])->toBe($request['expected_hash'])
        ->and($retried['workflow']->nodes)->toBeEmpty()
        ->and($retried['edit'])->toBe($created['edit']);
});

it('scopes every graph identifier and rolls back a batch containing a foreign node', function (): void {
    $other = Workflow::factory()->create();
    $foreign = app(WorkflowService::class)->addNode($other, 'core.delay');
    $local = app(WorkflowService::class)->addNode($this->workflow, 'core.delay');
    $before = $this->graphs->get($this->workflow)['hash'];
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'move_nodes', ['positions' => [
        ['node_id' => $local->id, 'position_x' => 100, 'position_y' => 200],
        ['node_id' => $foreign->id, 'position_x' => 100, 'position_y' => 200],
    ]]))->assertNotFound();
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'add_node', [
        ...graphStep(), 'source' => ['node_id' => $foreign->id, 'port' => 'main'], 'input_port' => 'main',
    ]))->assertNotFound();
    expect($this->graphs->get($this->workflow)['hash'])->toBe($before)
        ->and($foreign->refresh()->position_x)->toBe(0);
});

it('undoes a deletion with exact identities, positions and pins while retaining run history', function (): void {
    $node = app(WorkflowService::class)->addNode($this->workflow, 'core.delay', name: 'Wait', positionX: 73, positionY: -24);
    $node->update(['pinned_data' => ['output' => ['main' => [['answer' => 42]]]]]);
    $run = WorkflowRun::factory()->completed()->create(['workflow_id' => $this->workflow->id]);
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id, 'node_id' => $node->id]);
    $before = $this->graphs->get($this->workflow)['hash'];
    $removed = $this->graphs->edit($this->workflow, graphEditRequest($this->workflow, 'remove', ['node_ids' => [$node->id], 'edge_ids' => []]));
    $this->graphs->edit($this->workflow, graphEditRequest($this->workflow, 'undo', ['edit_id' => $removed['edit']['id']]));
    expect($this->graphs->get($this->workflow)['hash'])->toBe($before)
        ->and($node->refresh()->position_x)->toBe(73)
        ->and($nodeRun->refresh()->node_id)->toBe($node->id)
        ->and($this->workflow->refresh()->is_active)->toBeFalse()
        ->and($this->workflow->revisions()->count())->toBe(1)
        ->and($run->refresh()->workflow_revision_id)->toBe($nodeRun->workflowRun->workflow_revision_id);
});

it('uses real node-run input shape and provenance when pinning', function (): void {
    $node = app(WorkflowService::class)->addNode($this->workflow, 'core.delay');
    $run = WorkflowRun::factory()->completed()->create(['workflow_id' => $this->workflow->id]);
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id, 'node_id' => $node->id, 'input' => [['id' => 7]], 'output' => ['main' => [['id' => 7]]]]);
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'pin', [
        'node_id' => $node->id, 'source' => 'run', 'node_run_id' => $nodeRun->id,
    ]))->assertOk();
    expect($node->refresh()->pinned_data)->toBe(['input' => [['id' => 7]], 'output' => ['main' => [['id' => 7]]], 'source_run_id' => $run->id]);
});

it('keeps the live revision and its run unchanged when editing and undoing the draft', function (): void {
    $service = app(WorkflowService::class);
    $trigger = $service->addNode($this->workflow, 'core.manual');
    $node = $service->addNode($this->workflow, 'core.delay');
    $service->connect($trigger, $node);
    $live = $service->activate($this->workflow)->activeRevision;
    $run = WorkflowRun::factory()->create(['workflow_id' => $this->workflow->id, 'workflow_revision_id' => $live->id]);
    $definition = $live->definition;
    $edit = $this->graphs->edit($this->workflow, graphEditRequest($this->workflow, 'update_node', ['node_id' => $node->id, 'name' => 'A new name', 'config' => ['delay_value' => 10]]));
    $this->graphs->edit($this->workflow, graphEditRequest($this->workflow, 'undo', ['edit_id' => $edit['edit']['id']]));
    expect($this->workflow->refresh()->active_revision_id)->toBe($live->id)
        ->and($this->workflow->is_active)->toBeTrue()
        ->and($live->refresh()->definition)->toBe($definition)
        ->and($run->refresh()->workflow_revision_id)->toBe($live->id);
});

it('projects configured ports and refuses nonexistent switch outputs and incoming trigger edges', function (): void {
    $switch = $this->graphs->edit($this->workflow, graphEditRequest($this->workflow, 'add_node', [
        ...graphStep('core.switch'), 'config' => ['field' => 'priority', 'cases' => [['port' => 'urgent', 'operator' => 'equals', 'value' => 'high']]],
    ]));
    $source = $switch['edit']['created_node_id'];
    $target = app(WorkflowService::class)->addNode($this->workflow, 'core.delay');
    $trigger = app(WorkflowService::class)->addNode($this->workflow, 'core.manual');
    $this->getJson($this->url.'/graph')->assertOk()->assertJsonPath('data.workflow.nodes.0.output_ports', ['default', 'urgent']);
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'connect', [
        'source_node_id' => $source, 'target_node_id' => $target->id, 'source_port' => 'urgent', 'target_port' => 'main',
    ]))->assertOk();
    foreach ([[$target->id, 'case_fake'], [$trigger->id, 'default']] as [$id, $port]) {
        $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'connect', [
            'source_node_id' => $source, 'target_node_id' => $id, 'source_port' => $port, 'target_port' => 'main',
        ]))->assertUnprocessable();
    }
});

it('rejects a test for a stale graph or foreign step before creating a run', function (): void {
    $trigger = app(WorkflowService::class)->addNode($this->workflow, 'core.manual');
    $hash = $this->graphs->get($this->workflow)['hash'];
    app(WorkflowService::class)->updateNode($trigger, ['name' => 'Changed']);
    $this->postJson($this->url.'/test-node', ['node_id' => $trigger->id, 'payload' => [], 'expected_graph_hash' => $hash])->assertConflict();
    $other = Workflow::factory()->create();
    $foreign = app(WorkflowService::class)->addNode($other, 'core.manual');
    $this->postJson($this->url.'/test-node', ['node_id' => $foreign->id, 'payload' => []])->assertNotFound();
    expect($this->workflow->runs()->count())->toBe(0);
});

it('rejects an empty source as invalid input without creating a partial node', function (): void {
    $this->postJson($this->url.'/graph-edits', graphEditRequest($this->workflow, 'add_node', [
        ...graphStep(), 'source' => [],
    ]))->assertUnprocessable();
    expect($this->workflow->nodes()->count())->toBe(0)
        ->and($this->workflow->graphEdits()->count())->toBe(0);
});
