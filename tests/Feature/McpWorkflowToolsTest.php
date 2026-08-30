<?php

declare(strict_types=1);

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\WorkflowContext;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Mcp\Tools\AddWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ConnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\GetWorkflowDraftTool;
use Aitumalow\Mcp\Tools\ListWorkflowNodesTool;
use Aitumalow\Mcp\Tools\RunWorkflowTool;
use Aitumalow\Mcp\Tools\SaveWorkflowDraftTool;
use Aitumalow\Mcp\Tools\ShowWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ShowWorkflowRunTool;
use Aitumalow\Mcp\WorkflowMcpServer;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode as WorkflowNodeModel;
use Aitumalow\Models\WorkflowRun;
use Aitumalow\Services\WorkflowDraftService;
use Aitumalow\Services\WorkflowService;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Transport\FakeTransporter;

beforeEach(function (): void {
    WorkflowAutomation::register(McpCreateTicketAction::class);
});

it('exposes only the focused catalog and workflow composition tools', function (): void {
    $server = new WorkflowMcpServer(new FakeTransporter);
    $context = $server->createContext();

    expect($context->tools()->map->name()->all())->toBe([
        'list_workflow_nodes',
        'show_workflow_node',
        'list_workflow_references',
        'list_workflows',
        'show_workflow',
        'get_workflow_draft',
        'create_workflow',
        'save_workflow_draft',
        'update_workflow',
        'add_workflow_node',
        'update_workflow_node',
        'remove_workflow_node',
        'connect_workflow_nodes',
        'disconnect_workflow_nodes',
        'validate_workflow',
        'activate_workflow',
        'deactivate_workflow',
        'run_workflow',
        'show_workflow_run',
    ])->and($context->prompts())->toBeEmpty()
        ->and($context->instructions)->toContain('never activate, deactivate, or run a workflow unless the user explicitly asks');
});

it('round trips a complete draft atomically without changing the active revision', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNodeModel::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
    ]);
    $action = WorkflowNodeModel::factory()->create([
        'workflow_id' => $workflow->id,
        'node_key' => 'app.ticket.create',
        'name' => 'Create ticket',
        'config' => ['priority' => 'normal'],
    ]);
    app(WorkflowService::class)->connect($trigger, $action);
    $active = app(WorkflowService::class)->activate($workflow)->activeRevision;
    $draft = app(WorkflowDraftService::class)->get($workflow);

    WorkflowMcpServer::tool(GetWorkflowDraftTool::class, [
        'workflow_id' => $workflow->id,
    ])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('draft.draft_hash', $draft['draft_hash'])
            ->where('draft.nodes.0.id', 'node_1')
            ->where('draft.nodes.1.capability', 'app.ticket.create')
            ->where('draft.edges.0.from', 'node_1')
            ->where('draft.edges.0.to', 'node_2')
            ->etc());

    $draft['nodes'][1]['config']['priority'] = 'urgent';
    $draft['nodes'][1]['name'] = 'Create urgent ticket';

    WorkflowMcpServer::tool(SaveWorkflowDraftTool::class, [
        'workflow_id' => $workflow->id,
        'expected_draft_hash' => $draft['draft_hash'],
        'nodes' => $draft['nodes'],
        'edges' => $draft['edges'],
    ])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('draft.workflow.active_revision_id', $active?->id)
            ->where('draft.nodes.1.name', 'Create urgent ticket')
            ->where('draft.nodes.1.config.priority', 'urgent')
            ->etc());

    $workflow->refresh();
    $draftAction = $workflow->nodes()->where('node_key', 'app.ticket.create')->sole();
    $activePriority = null;
    $activeNodes = $workflow->activeRevision?->definition['nodes'] ?? [];
    if (is_array($activeNodes)) {
        foreach ($activeNodes as $activeNode) {
            if (is_array($activeNode) && ($activeNode['key'] ?? null) === 'app.ticket.create') {
                $activePriority = data_get($activeNode, 'config.priority');
            }
        }
    }

    expect($workflow->active_revision_id)->toBe($active?->id)
        ->and($workflow->revisions()->count())->toBe(1)
        ->and($draftAction->config)->toBe(['priority' => 'urgent'])
        ->and($activePriority)->toBe('normal');
});

it('rolls back invalid and stale whole-draft saves', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNodeModel::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
    ]);
    $action = WorkflowNodeModel::factory()->create([
        'workflow_id' => $workflow->id,
        'node_key' => 'app.ticket.create',
        'name' => 'Create ticket',
        'config' => ['priority' => 'normal'],
    ]);
    app(WorkflowService::class)->connect($trigger, $action);
    $drafts = app(WorkflowDraftService::class);
    $original = $drafts->get($workflow);
    $invented = $original['nodes'];
    $invented[0]['class'] = 'App\\Workflows\\VoiceAgent';

    WorkflowMcpServer::tool(SaveWorkflowDraftTool::class, [
        'workflow_id' => $workflow->id,
        'expected_draft_hash' => $original['draft_hash'],
        'nodes' => $invented,
        'edges' => $original['edges'],
    ])->assertHasErrors(['unknown fields: class']);

    WorkflowMcpServer::tool(SaveWorkflowDraftTool::class, [
        'workflow_id' => $workflow->id,
        'expected_draft_hash' => $original['draft_hash'],
        'nodes' => [$original['nodes'][1]],
        'edges' => [],
    ])->assertHasErrors(['must have at least one trigger node']);

    expect($drafts->get($workflow)['draft_hash'])->toBe($original['draft_hash']);

    $action->update(['name' => 'Changed elsewhere']);

    WorkflowMcpServer::tool(SaveWorkflowDraftTool::class, [
        'workflow_id' => $workflow->id,
        'expected_draft_hash' => $original['draft_hash'],
        'nodes' => $original['nodes'],
        'edges' => $original['edges'],
    ])->assertHasErrors(['changed after it was read']);

    expect($workflow->nodes()->where('node_key', 'app.ticket.create')->sole()->name)
        ->toBe('Changed elsewhere');
});

it('starts asynchronously and inspects a run without returning raw payloads', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNodeModel::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNodeModel::factory()->create([
        'workflow_id' => $workflow->id,
        'node_key' => 'app.ticket.create',
        'config' => ['priority' => 'normal'],
    ]);
    app(WorkflowService::class)->connect($trigger, $action);
    app(WorkflowService::class)->activate($workflow);

    WorkflowMcpServer::tool(RunWorkflowTool::class, [
        'workflow_id' => $workflow->id,
        'payload' => [['private_note' => 'do not return']],
    ])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('workflow_run.workflow_id', $workflow->id)
            ->where('inspection.tool', 'show_workflow_run')
            ->missing('workflow_run.node_runs')
            ->etc());

    $runId = WorkflowRun::query()->sole()->id;
    $this->drainDurableRun((string) $runId);

    WorkflowMcpServer::tool(ShowWorkflowRunTool::class, [
        'workflow_run_id' => $runId,
    ])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('workflow_run.status', 'completed')
            ->has('workflow_run.node_runs', 2)
            ->missing('workflow_run.initial_payload')
            ->missing('workflow_run.context')
            ->etc())
        ->assertDontSee('do not return');
});

it('projects registered workflow nodes as structured MCP content', function (): void {
    WorkflowMcpServer::tool(ListWorkflowNodesTool::class, ['category' => 'Application'])
        ->assertOk()
        ->assertName('list_workflow_nodes')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('workflow_nodes', 1)
            ->where('workflow_nodes.0.key', 'app.ticket.create')
            ->where('workflow_nodes.0.name', 'Create support ticket')
            ->where('workflow_nodes.0.category', 'Application')
            ->etc());

    WorkflowMcpServer::tool(ShowWorkflowNodeTool::class, ['key' => 'app.ticket.create'])
        ->assertOk()
        ->assertName('show_workflow_node')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('workflow_node.key', 'app.ticket.create')
            ->where('workflow_node.type', 'action')
            ->where('workflow_node.config_schema.0.key', 'priority')
            ->etc())
        ->assertDontSee(McpCreateTicketAction::class);
});

it('adds workflow nodes by exact stable key and applies schema defaults', function (): void {
    $workflow = Workflow::factory()->create();

    WorkflowMcpServer::tool(AddWorkflowNodeTool::class, [
        'workflow_id' => $workflow->id,
        'key' => 'app.ticket.create',
        'config' => [],
    ])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('workflow_node.key', 'app.ticket.create')
            ->where('workflow_node.name', 'Create support ticket')
            ->where('workflow_node.config.priority', 'normal')
            ->etc());

    expect($workflow->nodes()->sole())
        ->node_key->toBe('app.ticket.create')
        ->config->toBe(['priority' => 'normal']);
});

it('rejects invented keys and configuration fields before persistence', function (): void {
    $workflow = Workflow::factory()->create();

    WorkflowMcpServer::tool(AddWorkflowNodeTool::class, [
        'workflow_id' => $workflow->id,
        'key' => 'ticket.create',
        'config' => [],
    ])->assertHasErrors(['is not registered']);

    WorkflowMcpServer::tool(AddWorkflowNodeTool::class, [
        'workflow_id' => $workflow->id,
        'key' => 'app.ticket.create',
        'config' => ['model' => 'App\\Models\\Ticket'],
    ])->assertHasErrors(['is not defined']);

    WorkflowMcpServer::tool(AddWorkflowNodeTool::class, [
        'workflow_id' => $workflow->id,
        'key' => 'app.ticket.create',
        'config' => ['priority' => 'invented'],
    ])->assertHasErrors(['selected config.priority is invalid']);

    expect($workflow->nodes()->count())->toBe(0);
});

it('refuses to connect workflow nodes from different workflows', function (): void {
    $source = WorkflowNodeModel::factory()->create(['workflow_id' => Workflow::factory()->create()->id]);
    $target = WorkflowNodeModel::factory()->create(['workflow_id' => Workflow::factory()->create()->id]);

    WorkflowMcpServer::tool(ConnectWorkflowNodesTool::class, [
        'source_workflow_node_id' => $source->id,
        'target_workflow_node_id' => $target->id,
    ])->assertHasErrors(['must belong to the same workflow']);
});

#[WorkflowNode(
    key: 'app.ticket.create',
    name: 'Create support ticket',
    category: 'Application',
    icon: 'ticket',
)]
final class McpCreateTicketAction implements WorkflowAction
{
    public function schema(): array
    {
        return [
            ['key' => 'priority', 'type' => 'select', 'label' => 'Priority', 'options' => ['normal', 'urgent'], 'default' => 'normal'],
        ];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'ticket_id', 'type' => 'integer', 'label' => 'Ticket ID'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        return ['ticket_id' => 123, 'priority' => $context->get('priority')];
    }
}
