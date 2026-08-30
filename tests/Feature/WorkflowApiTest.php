<?php

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Services\WorkflowService;

// ── List / Show ──────────────────────────────────────────────────

it('lists workflows', function () {
    Workflow::factory()->count(3)->create();

    $this->getJson('/workflow-engine/workflows')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('shows a workflow with nodes and edges', function () {
    $workflow = Workflow::factory()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    WorkflowNode::factory()->action('core.set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);

    $this->getJson("/workflow-engine/workflows/{$workflow->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $workflow->id)
        ->assertJsonCount(2, 'data.nodes');
});

// ── Create / Update / Delete ─────────────────────────────────────

it('creates a workflow', function () {
    $this->postJson('/workflow-engine/workflows', [
        'key' => 'test.workflow',
        'name' => 'Test Workflow',
        'description' => 'A test workflow',
    ])
        ->assertCreated()
        ->assertJsonPath('data.key', 'test.workflow')
        ->assertJsonPath('data.name', 'Test Workflow');

    $this->assertDatabaseHas(config('aitumalow.tables.workflows'), [
        'key' => 'test.workflow',
        'name' => 'Test Workflow',
    ]);

    expect(app(WorkflowService::class)->findByKey('test.workflow')->name)
        ->toBe('Test Workflow');
});

it('validates required fields on create', function () {
    $this->postJson('/workflow-engine/workflows', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates a workflow', function () {
    $workflow = Workflow::factory()->create(['name' => 'Old Name']);

    $this->putJson("/workflow-engine/workflows/{$workflow->id}", [
        'name' => 'New Name',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

it('deletes a workflow', function () {
    $workflow = Workflow::factory()->create();

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}")
        ->assertOk();

    $this->assertSoftDeleted(config('aitumalow.tables.workflows'), ['id' => $workflow->id]);
});

// ── Activate / Deactivate ────────────────────────────────────────

it('activates a workflow', function () {
    $workflow = Workflow::factory()->create(['is_active' => false]);
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('deactivates a workflow', function () {
    $workflow = Workflow::factory()->active()->create();

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('reactivates a deactivated workflow', function () {
    $workflow = Workflow::factory()->create(['is_active' => false]);
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/activate")
        ->assertOk();

    $revisionId = $workflow->fresh()->active_revision_id;

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.active_revision_id', $revisionId);

    expect($workflow->revisions()->count())->toBe(1);
});

it('refuses to activate an invalid workflow', function () {
    $workflow = Workflow::factory()->create(['is_active' => false]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/activate")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Workflow validation failed.')
        ->assertJsonPath('errors.0', 'Workflow must have at least one trigger node.');

    expect($workflow->fresh()->is_active)->toBeFalse();
});

// ── Duplicate ────────────────────────────────────────────────────

it('duplicates a workflow with nodes and edges', function () {
    $workflow = Workflow::factory()->active()->create(['name' => 'Original']);
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNode::factory()->action('core.set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.name', 'Original (Copy)')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonCount(2, 'data.nodes')
        ->assertJsonCount(1, 'data.edges');

    expect(Workflow::query()->where('name', 'Original (Copy)')->sole()->key)
        ->not->toBe($workflow->key);
});

// ── Validate ─────────────────────────────────────────────────────

it('validates a valid workflow', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $action = WorkflowNode::factory()->action('core.set_fields')->withConfig(['fields' => ['x' => 'y']])->create([
        'workflow_id' => $workflow->id, 'name' => 'action',
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/validate")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('errors', []);
});

it('returns errors for invalid workflow', function () {
    $workflow = Workflow::factory()->create();
    // No trigger = invalid

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/validate")
        ->assertUnprocessable()
        ->assertJsonPath('valid', false);
});

// ── Nodes ────────────────────────────────────────────────────────

it('adds a node to a workflow', function () {
    $workflow = Workflow::factory()->create();

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes", [
        'node_key' => 'core.manual',
        'name' => 'My Trigger',
    ])
        ->assertCreated()
        ->assertJsonPath('data.node_key', 'core.manual')
        ->assertJsonPath('data.name', 'My Trigger');
});

it('adds loop and delay nodes from their catalog defaults', function () {
    $workflow = Workflow::factory()->create();
    $catalog = $this->getJson('/workflow-engine/catalog')
        ->assertOk()
        ->json('data');

    foreach (['core.loop', 'core.delay'] as $nodeKey) {
        $schema = collect($catalog)
            ->firstWhere('key', $nodeKey)['config_schema'];
        $config = collect($schema)
            ->filter(fn (array $field): bool => array_key_exists('default', $field))
            ->mapWithKeys(fn (array $field): array => [$field['key'] => $field['default']])
            ->all();

        $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes", [
            'node_key' => $nodeKey,
            'config' => $config,
        ])
            ->assertCreated()
            ->assertJsonPath('data.node_key', $nodeKey)
            ->assertJsonPath('data.config', $config);
    }
});

it('rejects unnamespaced node keys', function () {
    $workflow = Workflow::factory()->create();

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes", [
        'node_key' => 'manual',
        'name' => 'Invalid trigger',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['node_key']);
});

it('rejects unknown node key', function () {
    $workflow = Workflow::factory()->create();

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes", [
        'node_key' => 'completely_fake',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['node_key']);
});

it('updates a node', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Old']);

    $this->putJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}", [
        'name' => 'New Name',
        'config' => [],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

it('deletes a node', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}")
        ->assertOk();

    $this->assertDatabaseMissing(config('aitumalow.tables.nodes'), ['id' => $node->id]);
});

it('updates node position', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->patchJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}/position", [
        'position_x' => 100,
        'position_y' => 200,
    ])
        ->assertOk()
        ->assertJsonPath('data.position_x', 100)
        ->assertJsonPath('data.position_y', 200);
});

// ── Edges ────────────────────────────────────────────────────────

it('creates an edge', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNode::factory()->action('core.set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/edges", [
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.source_node_id', $trigger->id)
        ->assertJsonPath('data.target_node_id', $action->id);
});

it('rejects self-referencing edge', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/edges", [
        'source_node_id' => $node->id,
        'target_node_id' => $node->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target_node_id']);
});

it('deletes an edge', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNode::factory()->action('core.set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);
    $edge = WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}/edges/{$edge->id}")
        ->assertOk();

    $this->assertDatabaseMissing(config('aitumalow.tables.edges'), ['id' => $edge->id]);
});

// ── Capability catalog ───────────────────────────────────────────

it('lists all available capabilities', function () {
    $this->getJson('/workflow-engine/catalog')
        ->assertOk()
        ->assertJsonStructure(['data' => [['key', 'namespace', 'name', 'category', 'icon', 'description', 'type', 'input_ports', 'output_ports', 'config_schema']]]);
});
