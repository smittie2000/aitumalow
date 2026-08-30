<?php

declare(strict_types=1);

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\WorkflowContext;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Mcp\Tools\AddWorkflowNodeTool;
use Aitumalow\Mcp\Tools\ConnectWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ListWorkflowNodesTool;
use Aitumalow\Mcp\Tools\ShowWorkflowNodeTool;
use Aitumalow\Mcp\WorkflowMcpServer;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode as WorkflowNodeModel;
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
        'create_workflow',
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
    ])->and($context->prompts())->toBeEmpty();
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
