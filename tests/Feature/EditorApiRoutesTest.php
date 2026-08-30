<?php

declare(strict_types=1);

use Aitumalow\Enums\NodeType;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode;
use Illuminate\Support\Facades\Route;

it('registers the editor API only through the host-owned route group', function (): void {
    $route = Route::getRoutes()->getByName('aitumalow.workflows.index');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('workflow-engine/workflows')
        ->and($route?->middleware())->toContain('api');
});

it('does not expose package credential endpoints', function (): void {
    expect(Route::getRoutes()->getByName('aitumalow.credentials.index'))->toBeNull();

    $this->getJson('/workflow-engine/credentials')->assertNotFound();
    $this->getJson('/workflow-engine/credentials-types')->assertNotFound();
});

it('scopes nested node bindings to their workflow', function (): void {
    $workflow = Workflow::factory()->create();
    $otherWorkflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->create([
        'workflow_id' => $otherWorkflow->id,
        'type' => NodeType::Action,
        'node_key' => 'core.set_fields',
    ]);

    $this->putJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}", [
        'name' => 'Cross-workflow update',
    ])->assertNotFound();

    expect($node->refresh()->name)->not->toBe('Cross-workflow update');
});
