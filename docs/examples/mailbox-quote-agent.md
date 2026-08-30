<div v-pre>

# Mailbox Quote Agent

This example polls a host-owned mailbox, classifies quote requests, delegates them to a host-owned agent, and notifies the host lead owner.

```text
core.schedule
  -> app.mailbox.scan
  -> core.loop(message_refs)
  -> app.mail.quote_intent
  -> core.if_condition(intent = quote_request)
  -> app.quote.delegate
  -> app.lead_owner.notify
```

## Ownership

Aitumalow owns the cron definition, durable occurrence, graph traversal, loop, branch, run history, and retry dispatch. The host owns mailbox credentials, mailbox cursor and claim lease, message bodies, host lookup, quote agent/model/provider, notification delivery, and business idempotency.

The graph carries `message_ref`, `lead_ref`, and `delegation_ref`. It does not carry an email body, access token, password, provider key, or model configuration.

## Headless execution scope

Scheduled workflows have no authenticated HTTP user. Bind a resolver which derives opaque host authority from the workflow:

```php
use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\Models\Workflow;

final class CrmWorkflowScopeResolver implements ExecutionScopeResolver
{
    public function resolve(Workflow $workflow): ExecutionScope
    {
        $owner = $workflow->owner; // Host-owned relation/model policy.

        return new ExecutionScope(
            tenantReference: "tenant:{$owner->tenant_ulid}",
            principalReference: "workflow-owner:{$owner->ulid}",
        );
    }
}

$this->app->bind(ExecutionScopeResolver::class, CrmWorkflowScopeResolver::class);
```

The package snapshots these two non-secret references on every workflow run. Resume, retry, and child workflows preserve that scope.

## Selectable host resources

Register safe options when administrators may choose among several mailboxes:

```php
use Aitumalow\Contracts\WorkflowReferenceProvider;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\ReferenceOption;
use Aitumalow\Facades\WorkflowAutomation;

final class ReadableMailboxProvider implements WorkflowReferenceProvider
{
    public function options(ExecutionScope $scope): iterable
    {
        return Mailbox::query()
            ->forTenantReference($scope->tenantReference)
            ->where('incoming_enabled', true)
            ->get()
            ->map(fn (Mailbox $mailbox) => new ReferenceOption(
                value: $mailbox->ulid,
                label: $mailbox->display_name,
            ));
    }

    public function allows(string $reference, ExecutionScope $scope): bool
    {
        return Mailbox::query()
            ->forTenantReference($scope->tenantReference)
            ->where('ulid', $reference)
            ->where('incoming_enabled', true)
            ->exists();
    }
}

WorkflowAutomation::reference(
    'app.mailboxes.readable',
    ReadableMailboxProvider::class,
);
```

The action schema stores only the returned opaque value:

```php
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
```

The embedded editor loads these options through the host-mounted editor API. MCP clients use `list_workflow_references` with the workflow ID and exact schema source. Both surfaces receive labels and opaque values only.

## Host action

Laravel constructs the host action and injects the host service which owns encrypted credentials and provider clients:

```php
#[WorkflowNode(
    key: 'app.mailbox.scan',
    name: 'Scan mailbox',
    category: 'Support',
)]
final class ScanMailboxAction implements WorkflowAction
{
    public function __construct(private QuoteMailbox $mailbox) {}

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
        return [[
            'key' => 'message_refs',
            'type' => 'array',
            'label' => 'Message References',
        ]];
    }

    public function handle(WorkflowContext $context): array
    {
        return ['message_refs' => $this->mailbox->claimUnread(
            mailboxReference: (string) $context->get('mailbox'),
            scope: $context->scope(),
        )];
    }
}
```

`claimUnread` should use a provider UID/cursor and a renewable claim lease. Delegation and notification actions should accept `message_ref` as their idempotency key so retries return the existing result instead of producing a second quote or notification.

The complete executable fake-host version is covered by `tests/Feature/MailboxMonitoringWorkflowTest.php`.

</div>
