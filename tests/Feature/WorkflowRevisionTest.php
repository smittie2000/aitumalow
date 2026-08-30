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

it('restores a published revision into the draft without changing the live revision', function (): void {
    $workflow = Workflow::factory()->create([
        'settings' => ['retry_count' => 1],
    ]);
    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
        'position_x' => 120,
        'position_y' => 80,
    ]);
    $action = WorkflowNode::factory()->action('core.set_fields')->withConfig([
        'fields' => ['revision' => 'one'],
    ])->create([
        'workflow_id' => $workflow->id,
        'name' => 'Set revision',
        'position_x' => 420,
        'position_y' => 80,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $service = app(WorkflowService::class);
    $revisionOne = $service->publish($workflow);

    $action->update([
        'config' => ['fields' => ['revision' => 'two']],
        'position_x' => 720,
    ]);
    $workflow->update(['settings' => ['retry_count' => 2]]);
    $revisionTwo = $service->publish($workflow);
    $service->activate($workflow, $revisionTwo);

    $restored = $service->restoreDraft($workflow, $revisionOne);
    $restoredAction = $restored->nodes->firstWhere('name', 'Set revision');
    $restoredTrigger = $restored->nodes->firstWhere('name', 'Start');
    $restoredEdge = $restored->edges->sole();

    expect($restored->active_revision_id)->toBe($revisionTwo->id)
        ->and($restored->is_active)->toBeTrue()
        ->and($restored->settings)->toMatchArray(['retry_count' => 1])
        ->and($restoredAction?->config)->toBe(['fields' => ['revision' => 'one']])
        ->and($restoredAction?->position_x)->toBe(420)
        ->and($restoredTrigger?->position_x)->toBe(120)
        ->and($restoredEdge->source_node_id)->toBe($restoredTrigger?->id)
        ->and($restoredEdge->target_node_id)->toBe($restoredAction?->id)
        ->and($restoredAction?->id)->not->toBe($action->id);
});

it('lists revisions newest first and restores only revisions from the requested workflow', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $service = app(WorkflowService::class);
    $revisionOne = $service->publish($workflow, 'principal:first');
    $trigger->update(['name' => 'Changed trigger']);
    $revisionTwo = $service->publish($workflow, 'principal:second');
    $service->activate($workflow, $revisionTwo);

    $this->getJson("/workflow-engine/workflows/{$workflow->id}/revisions")
        ->assertOk()
        ->assertJsonPath('data.0.id', $revisionTwo->id)
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.0.is_active', true)
        ->assertJsonPath('data.1.id', $revisionOne->id)
        ->assertJsonPath('data.1.is_active', false);

    $this->getJson("/workflow-engine/workflows/{$workflow->id}/revisions/{$revisionOne->id}/compare-draft")
        ->assertOk()
        ->assertJsonPath('data.revision.id', $revisionOne->id)
        ->assertJsonPath('data.revision.version', 1)
        ->assertJsonPath('data.revision_definition.nodes.0.name', null)
        ->assertJsonPath('data.draft_definition.nodes.0.name', 'Changed trigger');

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/revisions/{$revisionOne->id}/restore-draft")
        ->assertOk()
        ->assertJsonPath('data.active_revision_id', $revisionTwo->id)
        ->assertJsonPath('data.nodes.0.name', null);

    $otherWorkflow = Workflow::factory()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $otherWorkflow->id]);
    $otherRevision = $service->publish($otherWorkflow);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/revisions/{$otherRevision->id}/restore-draft")
        ->assertNotFound();

    $this->getJson("/workflow-engine/workflows/{$workflow->id}/revisions/{$otherRevision->id}/compare-draft")
        ->assertNotFound();
});
