<?php

declare(strict_types=1);

use Aitumalow\Enums\NodeType;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Services\WorkflowService;

it('publishes immutable content-addressed revisions and runs only the active revision', function (): void {
    $workflow = Workflow::factory()->create(['name' => 'Versioned workflow']);
    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
    ]);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'name' => 'Set revision',
        'config' => ['fields' => ['revision' => 'one']],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $service = app(WorkflowService::class);
    $revisionOne = $service->publish($workflow, 'user:publisher');
    $sameRevision = $service->publish($workflow, 'user:publisher');
    $service->activate($workflow, $revisionOne);

    expect($revisionOne->version)->toBe(1)
        ->and($sameRevision->is($revisionOne))->toBeTrue()
        ->and($revisionOne->published_by_reference)->toBe('user:publisher')
        ->and($workflow->fresh()->active_revision_id)->toBe($revisionOne->id);

    $action->update(['config' => ['fields' => ['revision' => 'two']]]);

    $stillOne = $this->drainDurableRun($service->run($workflow->fresh(), [[]]));
    expect($stillOne->workflow_revision_id)->toBe($revisionOne->id)
        ->and($stillOne->nodeRuns->firstWhere('node_id', $action->id)?->output)
        ->toBe(['main' => [['revision' => 'one']]]);

    $revisionTwo = $service->publish($workflow);
    expect($revisionTwo->version)->toBe(2)
        ->and($revisionTwo->definition_hash)->not->toBe($revisionOne->definition_hash)
        ->and($workflow->fresh()->active_revision_id)->toBe($revisionOne->id);

    $service->activate($workflow, $revisionTwo);
    $nowTwo = $this->drainDurableRun($service->run($workflow->fresh(), [[]]));

    expect($nowTwo->workflow_revision_id)->toBe($revisionTwo->id)
        ->and($nowTwo->nodeRuns->firstWhere('node_id', $action->id)?->output)
        ->toBe(['main' => [['revision' => 'two']]]);

    $replay = $this->drainDurableRun($service->replay($stillOne));
    expect($replay->workflow_revision_id)->toBe($revisionOne->id)
        ->and($replay->nodeRuns->firstWhere('node_id', $action->id)?->output)
        ->toBe(['main' => [['revision' => 'one']]]);
});

it('keeps an active revision executable after its mutable draft node is removed', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNode::factory()->action('core.set_fields')->withConfig([
        'fields' => ['preserved' => true],
    ])->create(['workflow_id' => $workflow->id]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $service->removeNode($action->id);

    $run = $this->drainDurableRun($service->run($workflow->fresh(), [[]]));

    expect($run->nodeRuns->firstWhere('node_id', $action->id)?->output)
        ->toBe(['main' => [['preserved' => true]]])
        ->and($run->nodeRuns->firstWhere('node_id', $action->id)?->node)->toBeNull();
});
