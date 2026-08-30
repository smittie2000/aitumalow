<?php

declare(strict_types=1);

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\Contracts\WorkflowReferenceProvider;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\ReferenceOption;
use Aitumalow\DTOs\WorkflowContext;
use Aitumalow\Enums\RunStatus;
use Aitumalow\Facades\Workflow;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Mcp\Tools\ListWorkflowReferencesTool;
use Aitumalow\Mcp\WorkflowMcpServer;
use Aitumalow\Models\Workflow as WorkflowModel;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Validation\ValidationException;
use Workflow\V2\Models\WorkflowSchedule;
use Workflow\V2\Support\ScheduleManager;

beforeEach(function (): void {
    app()->bind(ExecutionScopeResolver::class, TenantWorkflowScopeResolver::class);
    app()->singleton(FakeHostMailbox::class);
    app()->singleton(FakeQuoteAgent::class);
    app()->singleton(FakeLeadNotifier::class);

    WorkflowAutomation::reference('app.mailboxes.readable', QuoteMailboxReferenceProvider::class);
    WorkflowAutomation::reference('app.quote_agents.available', QuoteAgentReferenceProvider::class);
    WorkflowAutomation::register(ScanQuoteMailboxAction::class);
    WorkflowAutomation::register(ClassifyQuoteRequestAction::class);
    WorkflowAutomation::register(DelegateQuoteRequestAction::class);
    WorkflowAutomation::register(NotifyLeadOwnerAction::class);
});

it('runs one durable mailbox occurrence without persisting messages or credentials', function (): void {
    Carbon::setTestNow('2026-08-29 09:00:00 UTC');

    $workflow = quoteMailboxWorkflow();
    $schedule = WorkflowSchedule::query()
        ->where('schedule_id', 'aitumalow.workflow.'.$workflow->id)
        ->sole();
    $nextFireAt = $schedule->getAttribute('next_fire_at');
    if (! $nextFireAt instanceof Carbon) {
        throw new RuntimeException('Durable schedule has no next fire time.');
    }

    Carbon::setTestNow($nextFireAt->copy()->addSecond());
    $tick = ScheduleManager::tick();

    expect($tick)->toHaveCount(1)
        ->and($tick[0]['outcome'])->toBe('triggered');

    $run = $this->drainDurableRun($tick[0]['run_id']);
    $serializedRun = json_encode([
        'payload' => $run->initial_payload,
        'context' => $run->context,
        'nodes' => $run->nodeRuns()->get(['input', 'output'])->toArray(),
    ], JSON_THROW_ON_ERROR);

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->execution_scope)->toBe([
            'tenant_reference' => 'tenant:acme',
            'principal_reference' => 'service:workflow-runner',
        ])
        ->and((int) $schedule->fresh()->getAttribute('fires_count'))->toBe(1)
        ->and(app(FakeQuoteAgent::class)->delegations)->toBe([
            'mail:quotes:uid:101' => 'quote:mail:quotes:uid:101',
        ])
        ->and(app(FakeLeadNotifier::class)->notifications)->toBe([
            'mail:quotes:uid:101' => 'lead:42',
        ])
        ->and($serializedRun)
        ->not->toContain('Please quote 12 routers')
        ->not->toContain('Ordinary status update')
        ->not->toContain('mailbox-password')
        ->not->toContain('provider-api-key')
        ->and(ScheduleManager::tick())->toBeEmpty()
        ->and($workflow->runs()->count())->toBe(1)
        ->and((int) $schedule->fresh()->getAttribute('fires_count'))->toBe(1);
});

it('lists only scoped opaque host references and rejects an unauthorized value', function (): void {
    $workflow = WorkflowModel::factory()->create();

    $this->getJson("/workflow-engine/workflows/{$workflow->id}/references/app.mailboxes.readable")
        ->assertOk()
        ->assertExactJson(['data' => [[
            'value' => 'quotes-inbox',
            'label' => 'Quotes inbox',
            'description' => 'Inbound quote requests',
        ]]])
        ->assertJsonMissing(['password' => 'mailbox-password']);

    WorkflowMcpServer::tool(ListWorkflowReferencesTool::class, [
        'workflow_id' => $workflow->id,
        'source' => 'app.mailboxes.readable',
    ])->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('references.0.value', 'quotes-inbox')
            ->where('references.0.label', 'Quotes inbox')
            ->missing('references.0.password')
            ->etc());

    expect(fn () => Workflow::addNode(
        $workflow,
        'app.mailbox.scan',
        ['mailbox' => 'another-tenant-mailbox'],
    ))->toThrow(ValidationException::class);
});

it('rejects invalid cron and timezone configuration before persistence', function (): void {
    $workflow = WorkflowModel::factory()->create();

    expect(fn () => Workflow::addNode($workflow, 'core.schedule', [
        'cron' => 'not a cron',
        'timezone' => 'UTC',
    ]))->toThrow(ValidationException::class)
        ->and(fn () => Workflow::addNode($workflow, 'core.schedule', [
            'cron' => '0 9 * * *',
            'timezone' => 'Somewhere/Imaginary',
        ]))->toThrow(ValidationException::class)
        ->and($workflow->nodes()->count())->toBe(0);
});

function quoteMailboxWorkflow(): WorkflowModel
{
    $workflow = Workflow::create([
        'name' => 'Monitor quote mailbox',
    ]);

    $schedule = Workflow::addNode($workflow, 'core.schedule', [
        'cron' => '*/5 * * * *',
        'timezone' => 'UTC',
    ], 'Every five minutes');
    $scan = Workflow::addNode($workflow, 'app.mailbox.scan', [
        'mailbox' => 'quotes-inbox',
    ], 'Claim unread messages');
    $loop = Workflow::addNode($workflow, 'core.loop', [
        'source_field' => 'message_refs',
    ], 'For each message');
    $classify = Workflow::addNode($workflow, 'app.mail.quote_intent', [], 'Classify safely');
    $condition = Workflow::addNode($workflow, 'core.if_condition', [
        'field' => 'intent',
        'operator' => 'equals',
        'value' => 'quote_request',
    ], 'Is a quote request?');
    $delegate = Workflow::addNode($workflow, 'app.quote.delegate', [
        'agent' => 'default-quote-agent',
    ], 'Delegate once');
    $notify = Workflow::addNode($workflow, 'app.lead_owner.notify', [], 'Notify lead owner once');

    Workflow::connect($schedule, $scan);
    Workflow::connect($scan, $loop);
    Workflow::connect($loop, $classify, 'loop_item');
    Workflow::connect($classify, $condition);
    Workflow::connect($condition, $delegate, 'true');
    Workflow::connect($delegate, $notify);
    Workflow::activate($workflow);

    return $workflow->fresh();
}

final class TenantWorkflowScopeResolver implements ExecutionScopeResolver
{
    public function resolve(WorkflowModel $workflow): ExecutionScope
    {
        return new ExecutionScope(
            tenantReference: 'tenant:acme',
            principalReference: 'service:workflow-runner',
        );
    }
}

final class QuoteMailboxReferenceProvider implements WorkflowReferenceProvider
{
    public function options(ExecutionScope $scope): iterable
    {
        return $scope->tenantReference === 'tenant:acme'
            ? [new ReferenceOption('quotes-inbox', 'Quotes inbox', 'Inbound quote requests')]
            : [];
    }

    public function allows(string $reference, ExecutionScope $scope): bool
    {
        return $scope->tenantReference === 'tenant:acme' && $reference === 'quotes-inbox';
    }
}

final class QuoteAgentReferenceProvider implements WorkflowReferenceProvider
{
    public function options(ExecutionScope $scope): iterable
    {
        return $scope->tenantReference === 'tenant:acme'
            ? [new ReferenceOption('default-quote-agent', 'Default quote agent')]
            : [];
    }

    public function allows(string $reference, ExecutionScope $scope): bool
    {
        return $scope->tenantReference === 'tenant:acme' && $reference === 'default-quote-agent';
    }
}

final class FakeHostMailbox
{
    /** @var array<string, array{body: string, lead_reference: string}> */
    private array $messages = [
        'mail:quotes:uid:101' => ['body' => 'Please quote 12 routers', 'lead_reference' => 'lead:42'],
        'mail:quotes:uid:102' => ['body' => 'Ordinary status update', 'lead_reference' => 'lead:84'],
    ];

    /** @var array<string, true> */
    private array $claimed = [];

    /** @return array<int, string> */
    public function claimUnread(string $mailbox, ExecutionScope $scope): array
    {
        if ($mailbox !== 'quotes-inbox' || $scope->tenantReference !== 'tenant:acme') {
            throw new RuntimeException('Mailbox access denied.');
        }

        $references = array_values(array_diff(array_keys($this->messages), array_keys($this->claimed)));

        foreach ($references as $reference) {
            $this->claimed[$reference] = true;
        }

        return $references;
    }

    /** @return array{body: string, lead_reference: string} */
    public function message(string $reference, ExecutionScope $scope): array
    {
        if ($scope->tenantReference !== 'tenant:acme' || ! isset($this->messages[$reference])) {
            throw new RuntimeException('Message access denied.');
        }

        return $this->messages[$reference];
    }
}

final class FakeQuoteAgent
{
    /** @var array<string, string> */
    public array $delegations = [];

    public function delegateOnce(string $messageReference, string $agent, ExecutionScope $scope): string
    {
        if ($agent !== 'default-quote-agent' || $scope->tenantReference !== 'tenant:acme') {
            throw new RuntimeException('Agent delegation denied.');
        }

        return $this->delegations[$messageReference] ??= "quote:{$messageReference}";
    }
}

final class FakeLeadNotifier
{
    /** @var array<string, string> */
    public array $notifications = [];

    public function notifyOnce(string $messageReference, string $leadReference, ExecutionScope $scope): void
    {
        if ($scope->tenantReference !== 'tenant:acme') {
            throw new RuntimeException('Lead notification denied.');
        }

        $this->notifications[$messageReference] ??= $leadReference;
    }
}

#[WorkflowNode(key: 'app.mailbox.scan', name: 'Scan mailbox', category: 'Application')]
final readonly class ScanQuoteMailboxAction implements WorkflowAction
{
    public function __construct(private FakeHostMailbox $mailbox) {}

    public function schema(): array
    {
        return [[
            'key' => 'mailbox',
            'type' => 'reference',
            'source' => 'app.mailboxes.readable',
            'label' => 'Mailbox',
            'required' => true,
        ]];
    }

    public function outputSchema(): array
    {
        return [['key' => 'message_refs', 'type' => 'array', 'label' => 'Message References']];
    }

    public function handle(WorkflowContext $context): array
    {
        return ['message_refs' => array_map(
            fn (string $reference): array => ['message_ref' => $reference],
            $this->mailbox->claimUnread((string) $context->get('mailbox'), $context->scope()),
        )];
    }
}

#[WorkflowNode(key: 'app.mail.quote_intent', name: 'Classify quote intent', category: 'Application')]
final readonly class ClassifyQuoteRequestAction implements WorkflowAction
{
    public function __construct(private FakeHostMailbox $mailbox) {}

    public function schema(): array
    {
        return [];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'message_ref', 'type' => 'string', 'label' => 'Message Reference'],
            ['key' => 'intent', 'type' => 'string', 'label' => 'Intent'],
            ['key' => 'lead_ref', 'type' => 'string', 'label' => 'Lead Reference'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        $reference = (string) $context->get('_loop_item.message_ref');
        $message = $this->mailbox->message($reference, $context->scope());

        return [
            'message_ref' => $reference,
            'intent' => str_contains($message['body'], 'Please quote') ? 'quote_request' : 'other',
            'lead_ref' => $message['lead_reference'],
        ];
    }
}

#[WorkflowNode(key: 'app.quote.delegate', name: 'Delegate quote request', category: 'Application')]
final readonly class DelegateQuoteRequestAction implements WorkflowAction
{
    public function __construct(private FakeQuoteAgent $agent) {}

    public function schema(): array
    {
        return [[
            'key' => 'agent',
            'type' => 'reference',
            'source' => 'app.quote_agents.available',
            'label' => 'Quote Agent',
            'required' => true,
        ]];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'message_ref', 'type' => 'string', 'label' => 'Message Reference'],
            ['key' => 'lead_ref', 'type' => 'string', 'label' => 'Lead Reference'],
            ['key' => 'delegation_ref', 'type' => 'string', 'label' => 'Delegation Reference'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        $messageReference = (string) $context->get('message_ref');

        return [
            'message_ref' => $messageReference,
            'lead_ref' => (string) $context->get('lead_ref'),
            'delegation_ref' => $this->agent->delegateOnce(
                $messageReference,
                (string) $context->get('agent'),
                $context->scope(),
            ),
        ];
    }
}

#[WorkflowNode(key: 'app.lead_owner.notify', name: 'Notify lead owner', category: 'Application')]
final readonly class NotifyLeadOwnerAction implements WorkflowAction
{
    public function __construct(private FakeLeadNotifier $notifier) {}

    public function schema(): array
    {
        return [];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'message_ref', 'type' => 'string', 'label' => 'Message Reference'],
            ['key' => 'notification_status', 'type' => 'string', 'label' => 'Notification Status'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        $messageReference = (string) $context->get('message_ref');
        $this->notifier->notifyOnce(
            $messageReference,
            (string) $context->get('lead_ref'),
            $context->scope(),
        );

        return [
            'message_ref' => $messageReference,
            'notification_status' => 'sent',
        ];
    }
}
