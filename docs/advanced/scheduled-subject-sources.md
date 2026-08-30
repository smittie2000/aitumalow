# Scheduled host subjects

Use `core.host_schedule` when one schedule discovers multiple host-owned
subjects, such as each Calendar booking due for confirmation. Aitumalow owns the
Durable schedule and starts one independently idempotent workflow run per
occurrence; the host owns only discovery and subject authorization.

Register a `ScheduledSubjectSource` through
`WorkflowAutomation::scheduledSource()` or a plugin context. The adapter
supplies its stable key and subject type, validates opaque configuration, maps
that configuration to cron/timezone, and yields
`ScheduledWorkflowOccurrence` DTOs.

```php
use Aitumalow\DTOs\ExecutionScope;

WorkflowAutomation::scheduledSource(DailyBookings::class);

yield new ScheduledWorkflowOccurrence(
    subjectReference: (string) $booking->ulid,
    idempotencyKey: $localDate.':'.$booking->ulid,
    payload: [['timezone' => $timezone]],
    executorReference: 'ai-agent:booking-agent',
    scope: new ExecutionScope(principalReference: 'system:booking-confirmations'),
);
```

An occurrence may carry its own `ExecutionScope`. This is the narrow seam for a
host-authorized scheduled principal; it avoids installing a global system scope
resolver that would broaden every workflow start.

The workflow trigger node stores only:

```php
[
    'source' => 'app.daily_bookings',
    'configuration' => ['time' => '08:00', 'timezone' => 'Africa/Johannesburg'],
]
```

The schedule launches Aitumalow's private dispatcher workflow. Its discovery
activity resolves the registered adapter and calls the same public, authorized,
idempotent start seam used by manual callers. Hosts do not create trigger
tables, poll schedules, keep occurrence ledgers, or import Durable Workflow.
