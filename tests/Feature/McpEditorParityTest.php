<?php

declare(strict_types=1);

use Aitumalow\Enums\RunStatus;
use Aitumalow\Mcp\Tools\AddWorkflowNodeTool;
use Aitumalow\Mcp\Tools\CompareWorkflowRevisionTool;
use Aitumalow\Mcp\Tools\ControlWorkflowRunTool;
use Aitumalow\Mcp\Tools\CreateWorkflowTool;
use Aitumalow\Mcp\Tools\DeleteWorkflowTool;
use Aitumalow\Mcp\Tools\DuplicateWorkflowTool;
use Aitumalow\Mcp\Tools\GetWorkflowVariablesTool;
use Aitumalow\Mcp\Tools\ListWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ListWorkflowOrganizationTool;
use Aitumalow\Mcp\Tools\ListWorkflowRevisionsTool;
use Aitumalow\Mcp\Tools\ListWorkflowRunsTool;
use Aitumalow\Mcp\Tools\ListWorkflowsTool;
use Aitumalow\Mcp\Tools\ManageWorkflowFolderTool;
use Aitumalow\Mcp\Tools\ManageWorkflowTagTool;
use Aitumalow\Mcp\Tools\RestoreWorkflowDraftTool;
use Aitumalow\Mcp\Tools\RunWorkflowTool;
use Aitumalow\Mcp\Tools\ShowWorkflowRunTool;
use Aitumalow\Mcp\Tools\TestWorkflowNodeTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowNodeTool;
use Aitumalow\Mcp\Tools\UpdateWorkflowTool;
use Aitumalow\Mcp\WorkflowMcpServer;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowFolder;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowTag;
use Aitumalow\Services\WorkflowGraphService;
use Aitumalow\Services\WorkflowService;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * A graph configured only from the existing package catalog.
 *
 * @return array<string, mixed>
 */
function mcpParityGraph(string $name = 'MCP parity'): array
{
    return ['name' => $name, 'nodes' => [
        ['id' => 'start', 'capability' => 'core.manual', 'name' => 'Start'],
        ['id' => 'set', 'capability' => 'core.set_fields', 'name' => 'Set value', 'config' => ['fields' => ['version' => 'one']]],
    ], 'edges' => [['from' => 'start', 'to' => 'set']]];
}

function mcpParityWorkflow(): Workflow
{
    WorkflowMcpServer::tool(CreateWorkflowTool::class, mcpParityGraph())->assertOk();

    return Workflow::query()->where('name', 'MCP parity')->sole();
}

it('creates a complete inactive draft with editor metadata in one call', function (): void {
    $folder = WorkflowFolder::factory()->create();
    $tag = WorkflowTag::factory()->create();
    WorkflowMcpServer::tool(CreateWorkflowTool::class, [...mcpParityGraph(),
        'folder_id' => $folder->id, 'tag_ids' => [$tag->id], 'settings' => ['max_concurrent_runs' => 3],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('workflow.is_active', false)->where('workflow.active_revision_id', null)
        ->has('draft.nodes', 2)->has('draft.edges', 1)->etc());
    $workflow = Workflow::query()->sole();
    expect($workflow->folder_id)->toBe($folder->id)->and($workflow->tags->modelKeys())->toBe([$tag->id])
        ->and($workflow->settings)->toBe(['max_concurrent_runs' => 3])->and($workflow->revisions()->count())->toBe(0)
        ->and($workflow->runs()->count())->toBe(0)
        ->and($workflow->nodes()->pluck('position_y')->unique()->count())->toBe(2);
    $this->getJson("/workflow-engine/workflows/{$workflow->id}")->assertOk()->assertJsonCount(2, 'data.nodes');
});

it('rolls back complete creation when graph or metadata validation fails', function (array $changes): void {
    WorkflowMcpServer::tool(CreateWorkflowTool::class, array_replace(mcpParityGraph(), $changes))->assertHasErrors();
    expect(Workflow::query()->count())->toBe(0)->and(WorkflowNode::query()->count())->toBe(0);
})->with([
    'unknown capability' => [['nodes' => [['id' => 'start', 'capability' => 'app.missing']], 'edges' => []]],
    'no trigger' => [['nodes' => [['id' => 'set', 'capability' => 'core.set_fields', 'config' => ['fields' => []]]], 'edges' => []]],
    'invalid port' => [['edges' => [['from' => 'start', 'to' => 'set', 'output' => 'missing']]]],
    'invalid name' => [['name' => str_repeat('x', 256)]],
    'missing folder' => [['folder_id' => 99999]],
]);

it('supports empty creation and trigger-only graphs but rejects half a graph', function (): void {
    WorkflowMcpServer::tool(CreateWorkflowTool::class, ['name' => 'Empty'])->assertOk();
    WorkflowMcpServer::tool(CreateWorkflowTool::class, ['name' => 'Trigger only', 'nodes' => [['id' => 'start', 'capability' => 'core.manual']], 'edges' => []])->assertOk();
    WorkflowMcpServer::tool(CreateWorkflowTool::class, ['name' => 'Missing edges', 'nodes' => [['id' => 'start', 'capability' => 'core.manual']]])->assertHasErrors();
    expect(Workflow::query()->count())->toBe(2);
});

it('discovers configuration schemas in one catalog request without implementation classes', function (): void {
    WorkflowMcpServer::tool(ListWorkflowNodesTool::class, ['search' => 'set fields', 'include_schemas' => true])
        ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->has('workflow_nodes', 1)->where('workflow_nodes.0.key', 'core.set_fields')
        ->has('workflow_nodes.0.config_schema')->has('workflow_nodes.0.output_ports')->missing('workflow_nodes.0.class')->etc());
});

it('shares folder and tag validation, assignment and list filters with the editor', function (): void {
    WorkflowMcpServer::tool(ManageWorkflowFolderTool::class, ['operation' => 'create', 'name' => 'Agent workflows'])->assertOk();
    $folder = WorkflowFolder::query()->sole();
    WorkflowMcpServer::tool(ManageWorkflowTagTool::class, ['operation' => 'create', 'name' => 'CRM', 'color' => '#123456'])->assertOk();
    $tag = WorkflowTag::query()->sole();
    $workflow = mcpParityWorkflow();
    WorkflowMcpServer::tool(UpdateWorkflowTool::class, ['workflow_id' => $workflow->id, 'description' => 'Updated by agent',
        'folder_id' => $folder->id, 'tag_ids' => [$tag->id], 'settings' => ['max_concurrent_runs' => 2],
    ])->assertOk();
    $query = ['folder_id' => $folder->id, 'tag_id' => $tag->id, 'search' => 'parity'];
    WorkflowMcpServer::tool(ListWorkflowsTool::class, $query)->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('pagination.total', 1)->where('workflows.0.id', $workflow->id)->where('workflows.0.nodes_count', 2)->where('workflows.0.edges_count', 1)->etc());
    $this->getJson('/workflow-engine/workflows?'.http_build_query($query))->assertJsonPath('data.0.id', $workflow->id);
    WorkflowMcpServer::tool(ListWorkflowOrganizationTool::class, ['tree' => true])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('folders.0.workflows_count', 1)->where('tags.0.workflows_count', 1)->etc());
    WorkflowMcpServer::tool(ManageWorkflowFolderTool::class, ['operation' => 'delete', 'id' => $folder->id])->assertHasErrors();
    WorkflowMcpServer::tool(ManageWorkflowTagTool::class, ['operation' => 'create', 'name' => 'CRM'])->assertHasErrors();
    WorkflowMcpServer::tool(UpdateWorkflowTool::class, ['workflow_id' => $workflow->id, 'description' => null, 'folder_id' => null, 'tag_ids' => []])->assertOk();
    expect($workflow->fresh()->description)->toBeNull()->and($workflow->fresh()->folder_id)->toBeNull()->and($workflow->tags()->count())->toBe(0);
    WorkflowMcpServer::tool(ManageWorkflowTagTool::class, ['operation' => 'update', 'id' => $tag->id, 'name' => 'Support', 'color' => null])->assertOk();
    WorkflowMcpServer::tool(ManageWorkflowFolderTool::class, ['operation' => 'update', 'id' => $folder->id, 'name' => 'Empty'])->assertOk();
    WorkflowMcpServer::tool(ManageWorkflowTagTool::class, ['operation' => 'delete', 'id' => $tag->id])->assertOk();
    WorkflowMcpServer::tool(ManageWorkflowFolderTool::class, ['operation' => 'delete', 'id' => $folder->id])->assertOk();
});

it('rejects folder cycles from MCP and keeps the editor tree intact', function (): void {
    $parent = WorkflowFolder::factory()->create();
    $child = WorkflowFolder::factory()->create(['parent_id' => $parent->id]);
    WorkflowMcpServer::tool(ManageWorkflowFolderTool::class, ['operation' => 'update', 'id' => $parent->id, 'parent_id' => $child->id])->assertHasErrors();
    expect($parent->fresh()->parent_id)->toBeNull();
    WorkflowMcpServer::tool(ManageWorkflowFolderTool::class, ['operation' => 'update', 'id' => $child->id, 'parent_id' => null])->assertOk();
    expect($child->fresh()->parent_id)->toBeNull();
});

it('returns the same expression variables as the editor and rejects unrelated nodes', function (): void {
    $workflow = mcpParityWorkflow();
    $node = $workflow->nodes()->where('node_key', 'core.set_fields')->sole();
    $variables = $this->getJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}/variables")->assertOk()->json();
    WorkflowMcpServer::tool(GetWorkflowVariablesTool::class, ['workflow_id' => $workflow->id, 'node_id' => $node->id])->assertOk()->assertStructuredContent($variables);
    $foreign = WorkflowNode::factory()->trigger()->create();
    WorkflowMcpServer::tool(GetWorkflowVariablesTool::class, ['workflow_id' => $workflow->id, 'node_id' => $foreign->id])->assertHasErrors();
});

it('reviews and restores revisions without changing the live version and scopes revisions to their workflow', function (): void {
    $workflow = mcpParityWorkflow();
    $service = app(WorkflowService::class);
    $first = $service->activate($workflow)->activeRevision ?? throw new RuntimeException('Missing revision.');
    $node = $workflow->nodes()->where('node_key', 'core.set_fields')->sole();
    $service->updateNode($node, ['config' => ['fields' => ['version' => 'two']]]);
    $second = $service->activate($workflow->fresh())->activeRevision ?? throw new RuntimeException('Missing revision.');
    WorkflowMcpServer::tool(ListWorkflowRevisionsTool::class, ['workflow_id' => $workflow->id])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('revisions.0.is_active', true)->where('revisions.1.is_active', false)->etc());
    WorkflowMcpServer::tool(CompareWorkflowRevisionTool::class, ['workflow_id' => $workflow->id, 'revision_id' => $first->id])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('revision_definition')->has('draft_definition')->where('revision.is_active', false)->etc());
    $other = Workflow::factory()->create();
    foreach ([CompareWorkflowRevisionTool::class, RestoreWorkflowDraftTool::class] as $tool) {
        WorkflowMcpServer::tool($tool, ['workflow_id' => $other->id, 'revision_id' => $first->id])->assertHasErrors();
    }
    WorkflowMcpServer::tool(RestoreWorkflowDraftTool::class, ['workflow_id' => $workflow->id, 'revision_id' => $first->id])->assertOk();
    expect($workflow->fresh()->active_revision_id)->toBe($second->id)
        ->and($workflow->nodes()->where('node_key', 'core.set_fields')->sole()->config)->toBe(['fields' => ['version' => 'one']]);
});

it('tests the draft with editor concurrency rules and exposes samples only when requested', function (): void {
    $workflow = mcpParityWorkflow();
    $service = app(WorkflowService::class);
    $live = $service->activate($workflow)->active_revision_id;
    $node = $workflow->nodes()->where('node_key', 'core.set_fields')->sole();
    $service->updateNode($node, ['config' => ['fields' => ['version' => 'draft']]]);
    $hash = app(WorkflowGraphService::class)->get($workflow)['hash'];
    WorkflowMcpServer::tool(TestWorkflowNodeTool::class, ['workflow_id' => $workflow->id, 'node_id' => $node->id,
        'expected_graph_hash' => str_repeat('0', 64), 'payload' => [['private' => 'sample']],
    ])->assertHasErrors();
    expect($workflow->runs()->count())->toBe(0);
    WorkflowMcpServer::tool(TestWorkflowNodeTool::class, ['workflow_id' => $workflow->id, 'node_id' => $node->id,
        'expected_graph_hash' => $hash, 'payload' => [['private' => 'sample']],
    ])->assertOk();
    $run = $this->drainDurableRun($workflow->runs()->sole());
    expect($run->status)->toBe(RunStatus::Completed)->and($workflow->fresh()->active_revision_id)->toBe($live)
        ->and($run->workflow_revision_id)->not->toBe($live);
    WorkflowMcpServer::tool(ShowWorkflowRunTool::class, ['workflow_run_id' => $run->id])->assertOk()->assertDontSee('sample');
    WorkflowMcpServer::tool(ShowWorkflowRunTool::class, ['workflow_run_id' => $run->id, 'include_data' => true])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('workflow_run.initial_payload.0.private', 'sample')->where('workflow_run.node_runs.1.output.main.0.version', 'draft')->etc());
    WorkflowMcpServer::tool(ListWorkflowRunsTool::class, ['workflow_id' => $workflow->id, 'status' => 'completed'])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('pagination.total', 1)->where('workflow_runs.0.id', $run->id)->etc());
    WorkflowMcpServer::tool(ListWorkflowRunsTool::class, ['workflow_id' => $workflow->id, 'per_page' => 101])->assertHasErrors();
});

it('replays the original revision even after new publication and supports waiting run controls', function (): void {
    $workflow = mcpParityWorkflow();
    $service = app(WorkflowService::class);
    $service->activate($workflow);
    $original = $this->drainDurableRun($service->run($workflow->fresh(), [[]]));
    $node = $workflow->nodes()->where('node_key', 'core.set_fields')->sole();
    $service->updateNode($node, ['config' => ['fields' => ['version' => 'two']]]);
    $service->activate($workflow->fresh());
    WorkflowMcpServer::tool(ControlWorkflowRunTool::class, ['workflow_run_id' => $original->id, 'action' => 'replay'])->assertOk();
    $replayed = $this->drainDurableRun($workflow->runs()->where('id', '!=', $original->id)->sole());
    expect($replayed->workflow_revision_id)->toBe($original->workflow_revision_id)
        ->and($replayed->nodeRuns->firstWhere('node_id', $node->id)?->output)->toBe(['main' => [['version' => 'one']]]);
    WorkflowMcpServer::tool(ControlWorkflowRunTool::class, ['workflow_run_id' => $original->id, 'action' => 'resume'])->assertHasErrors();
    WorkflowMcpServer::tool(ControlWorkflowRunTool::class, ['workflow_run_id' => $original->id, 'action' => 'replay', 'payload' => [[]]])->assertHasErrors();
    WorkflowMcpServer::tool(CreateWorkflowTool::class, ['name' => 'Wait', 'nodes' => [
        ['id' => 'start', 'capability' => 'core.manual'], ['id' => 'wait', 'capability' => 'core.wait_resume'],
        ['id' => 'after', 'capability' => 'core.set_fields', 'config' => ['fields' => ['resumed' => true]]],
    ], 'edges' => [['from' => 'start', 'to' => 'wait'], ['from' => 'wait', 'to' => 'after', 'output' => 'resume']]])->assertOk();
    $waiting = Workflow::query()->where('name', 'Wait')->sole();
    $service->activate($waiting);
    $run = $this->drainDurableRun($service->run($waiting, [[]]));
    WorkflowMcpServer::tool(ControlWorkflowRunTool::class, ['workflow_run_id' => $run->id, 'action' => 'resume', 'payload' => [['approved' => true]]])->assertOk();
    expect($this->drainDurableRun($run)->status)->toBe(RunStatus::Completed);
    $cancelled = $this->drainDurableRun($service->run($waiting, [[]]));
    WorkflowMcpServer::tool(ControlWorkflowRunTool::class, ['workflow_run_id' => $cancelled->id, 'action' => 'cancel'])->assertOk();
    expect($this->drainDurableRun($cancelled)->status)->toBe(RunStatus::Cancelled);
});

it('duplicates and deletes through the editor service without inheriting publication', function (): void {
    $workflow = mcpParityWorkflow();
    app(WorkflowService::class)->activate($workflow);
    WorkflowMcpServer::tool(DuplicateWorkflowTool::class, ['workflow_id' => $workflow->id])->assertOk();
    $copy = Workflow::query()->where('name', 'MCP parity (Copy)')->sole();
    expect($copy->is_active)->toBeFalse()->and($copy->active_revision_id)->toBeNull()
        ->and($copy->nodes()->count())->toBe(2)->and($copy->edges()->count())->toBe(1);
    WorkflowMcpServer::tool(DeleteWorkflowTool::class, ['workflow_id' => $copy->id])->assertOk();
    expect(Workflow::query()->find($copy->id))->toBeNull()->and($workflow->fresh()->is_active)->toBeTrue();
});

it('rejects malformed MCP configuration and payload instead of resetting settings or starting a run', function (): void {
    $workflow = mcpParityWorkflow();
    $node = $workflow->nodes()->where('node_key', 'core.set_fields')->sole();
    WorkflowMcpServer::tool(UpdateWorkflowNodeTool::class, ['workflow_node_id' => $node->id, 'config' => 'invalid'])->assertHasErrors();
    WorkflowMcpServer::tool(AddWorkflowNodeTool::class, ['workflow_id' => $workflow->id, 'key' => 'core.set_fields', 'config' => 'invalid'])->assertHasErrors();
    app(WorkflowService::class)->activate($workflow);
    WorkflowMcpServer::tool(RunWorkflowTool::class, ['workflow_id' => $workflow->id, 'payload' => ['invalid']])->assertHasErrors();
    expect($workflow->nodes()->count())->toBe(2)->and($node->fresh()->config)->toBe(['fields' => ['version' => 'one']])
        ->and($workflow->runs()->count())->toBe(0);
});
