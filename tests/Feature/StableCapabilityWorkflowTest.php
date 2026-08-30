<?php

declare(strict_types=1);

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\TriggerInterface;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\DTOs\WorkflowContext;
use Aitumalow\Enums\NodeType;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Facades\Workflow as WorkflowFacade;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Models\Workflow;
use Aitumalow\Nodes\BaseNode;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Runtime\ExecuteCapabilityActivity;
use Workflow\V2\Models\ActivityExecution;

it('builds and executes a host workflow entirely through stable capability keys', function (): void {
    WorkflowAutomation::register(WhatsAppConversationStartedTrigger::class);
    WorkflowAutomation::register(CreateCrmTicketAction::class);

    $catalog = $this->getJson('/workflow-engine/catalog')
        ->assertOk()
        ->assertJsonFragment([
            'key' => 'app.whatsapp.conversation_started',
            'namespace' => 'app',
            'type' => 'trigger',
        ])
        ->assertJsonFragment([
            'key' => 'app.ticket.create',
            'namespace' => 'app',
            'name' => 'Create support ticket',
            'category' => 'Application',
            'icon' => 'ticket',
            'type' => 'action',
        ])
        ->json();

    expect(json_encode($catalog, JSON_THROW_ON_ERROR))
        ->not->toContain(WhatsAppConversationStartedTrigger::class)
        ->not->toContain(CreateCrmTicketAction::class);

    $workflow = Workflow::factory()->create(['name' => 'Open ticket for WhatsApp conversation']);
    $trigger = WorkflowFacade::addNode(
        $workflow,
        'app.whatsapp.conversation_started',
        name: 'WhatsApp conversation started',
    );
    $action = WorkflowFacade::addNode(
        $workflow,
        'app.ticket.create',
        config: ['priority' => 'urgent'],
        name: 'Create support ticket',
    );

    WorkflowFacade::connect($trigger, $action);
    WorkflowFacade::activate($workflow);

    $run = $this->drainDurableRun(WorkflowFacade::run($workflow, [[
        'tenant_id' => 7,
        'conversation_id' => 42,
        'contact_id' => 99,
    ]]));

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->durable_workflow_id)->not->toBeNull()
        ->and($run->durable_run_id)->not->toBeNull()
        ->and($run->nodeRuns()->where('node_id', $action->id)->firstOrFail()->output)
        ->toBe([
            'main' => [[
                'tenant_id' => 7,
                'conversation_id' => 42,
                'contact_id' => 99,
                'priority' => 'urgent',
                'ticket_reference' => 'whatsapp:42',
            ]],
        ]);

    $hostExecutions = ActivityExecution::query()
        ->where('workflow_run_id', $run->durable_run_id)
        ->where('activity_class', ExecuteCapabilityActivity::class)
        ->get();

    expect($hostExecutions)->toHaveCount(2)
        ->and($hostExecutions->every(
            static fn (ActivityExecution $execution): bool => data_get(
                $execution->getAttribute('activity_options'),
                'execution_mode',
            ) !== 'local',
        ))->toBeTrue();
});

it('projects a host action schema to the SDK catalog and adapts its handler', function (): void {
    WorkflowAutomation::register(ProcessPaymentAction::class);

    $definition = app(NodeRegistry::class)
        ->definition('billing.payment.capture');

    expect($definition)
        ->toMatchArray([
            'key' => 'billing.payment.capture',
            'name' => 'Capture Payment',
            'category' => 'Billing',
            'icon' => 'credit-card',
            'type' => 'action',
        ])
        ->and($definition['config_schema'])->toBe([
            ['key' => 'gateway', 'type' => 'string', 'label' => 'Gateway', 'required' => true],
            ['key' => 'amount', 'type' => 'string', 'label' => 'Amount', 'placeholder' => '{{ item.order.total }}'],
        ])
        ->and($definition['output_schema'])->toBe(['main' => [
            ['key' => 'transaction_id', 'type' => 'string', 'label' => 'Transaction ID'],
            ['key' => 'status', 'type' => 'string', 'label' => 'Status'],
        ]]);

    $node = app(NodeRegistry::class)->resolve('billing.payment.capture');
    $output = $node->execute(
        new NodeInput(
            items: [['order' => ['id' => 10, 'total' => 125]]],
            context: new ExecutionContext(workflowRunId: 1, workflowId: 1),
        ),
        ['gateway' => 'stripe', 'amount' => 125],
    );

    expect($output->items())->toBe([[
        'transaction_id' => 'tx_10',
        'status' => 'paid',
        'gateway' => 'stripe',
        'amount' => 125,
    ]]);
});

it('lets Durable retry a host capability with one stable idempotency key', function (): void {
    FlakyCrmAction::reset();
    WorkflowAutomation::register(WhatsAppConversationStartedTrigger::class);
    WorkflowAutomation::register(FlakyCrmAction::class);

    $workflow = Workflow::factory()->create([
        'name' => 'Retry a host action',
        'settings' => [
            'retry_count' => 1,
            'retry_delay_ms' => 1000,
        ],
    ]);
    $trigger = WorkflowFacade::addNode($workflow, 'app.whatsapp.conversation_started');
    $action = WorkflowFacade::addNode($workflow, 'app.flaky.perform');
    WorkflowFacade::connect($trigger, $action);
    WorkflowFacade::activate($workflow);

    $run = $this->drainDurableRun(WorkflowFacade::run($workflow, [['record_id' => 42]]));
    $nodeRun = $run->nodeRuns()->where('node_id', $action->id)->firstOrFail();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($nodeRun->attempts)->toBe(2)
        ->and($nodeRun->status->value)->toBe('completed')
        ->and(FlakyCrmAction::$activityIds)->toHaveCount(2)
        ->and(FlakyCrmAction::$activityIds[0])->not->toBeNull()
        ->and(FlakyCrmAction::$activityIds[1])->toBe(FlakyCrmAction::$activityIds[0]);
});

#[WorkflowNode(
    key: 'app.whatsapp.conversation_started',
    name: 'WhatsApp conversation started',
    category: 'WhatsApp',
    icon: 'message-circle',
    type: NodeType::Trigger,
    description: 'Starts after the host verifies and stores a new WhatsApp conversation.',
)]
final class WhatsAppConversationStartedTrigger extends BaseNode implements TriggerInterface
{
    public function inputPorts(): array
    {
        return [];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'tenant_id', 'type' => 'integer', 'label' => 'Tenant ID'],
                ['key' => 'conversation_id', 'type' => 'integer', 'label' => 'Conversation ID'],
                ['key' => 'contact_id', 'type' => 'integer', 'label' => 'Contact ID'],
            ],
        ];
    }

    public function register(int $workflowId, int $nodeId, array $config): void {}

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return is_array($event) ? [$event] : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}

#[WorkflowNode(
    key: 'app.ticket.create',
    name: 'Create support ticket',
    category: 'Application',
    icon: 'ticket',
    description: 'Calls the host-owned ticket creation action.',
)]
final class CreateCrmTicketAction implements WorkflowAction
{
    public function schema(): array
    {
        return [
            ['key' => 'priority', 'type' => 'select', 'label' => 'Priority', 'options' => ['normal', 'urgent']],
        ];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'ticket_reference', 'type' => 'string', 'label' => 'Ticket Reference'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        return [
            ...$context->input(),
            'priority' => $context->get('priority', 'normal'),
            'ticket_reference' => 'whatsapp:'.$context->get('conversation_id'),
        ];
    }
}

#[WorkflowNode(
    key: 'billing.payment.capture',
    name: 'Capture Payment',
    category: 'Billing',
    icon: 'credit-card',
)]
final class ProcessPaymentAction implements WorkflowAction
{
    public function schema(): array
    {
        return [
            ['key' => 'gateway', 'type' => 'string', 'label' => 'Gateway', 'required' => true],
            ['key' => 'amount', 'type' => 'string', 'label' => 'Amount', 'placeholder' => '{{ item.order.total }}'],
        ];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'transaction_id', 'type' => 'string', 'label' => 'Transaction ID'],
            ['key' => 'status', 'type' => 'string', 'label' => 'Status'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        $order = $context->get('order');

        return [
            'transaction_id' => 'tx_'.$order['id'],
            'status' => 'paid',
            'gateway' => $context->get('gateway'),
            'amount' => $context->get('amount'),
        ];
    }
}

#[WorkflowNode(
    key: 'app.flaky.perform',
    name: 'Perform flaky host action',
    category: 'Application',
    icon: 'refresh-cw',
)]
final class FlakyCrmAction implements WorkflowAction
{
    /** @var array<int, string|null> */
    public static array $activityIds = [];

    public static function reset(): void
    {
        self::$activityIds = [];
    }

    public function schema(): array
    {
        return [];
    }

    public function outputSchema(): array
    {
        return [];
    }

    public function handle(WorkflowContext $context): array
    {
        self::$activityIds[] = $context->activityId();

        if (count(self::$activityIds) === 1) {
            throw new RuntimeException('Transient host failure.');
        }

        return [...$context->input(), 'attempts' => count(self::$activityIds)];
    }
}
