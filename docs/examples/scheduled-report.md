# Scheduled report

The host owns reporting and delivery. Aitumalow only composes their stable capability keys and Durable Workflow provides scheduling and execution.

```php
use Aitumalow\Facades\Workflow;
use Aitumalow\Facades\WorkflowAutomation;

WorkflowAutomation::register(FetchDailySales::class);   // sales.daily.fetch
WorkflowAutomation::register(DeliverSalesReport::class); // sales.report.deliver

$workflow = Workflow::create(['name' => 'Daily sales report']);

$schedule = Workflow::addNode($workflow, 'core.schedule', [
    'cron' => '0 8 * * *',
    'timezone' => 'UTC',
], 'Daily at 08:00');

$fetch = Workflow::addNode($workflow, 'sales.daily.fetch', [], 'Fetch scoped sales');
$filter = Workflow::addNode($workflow, 'core.filter', [
    'conditions' => [
        ['field' => 'revenue', 'operator' => 'greater_than', 'value' => 0],
    ],
], 'Keep revenue');
$deliver = Workflow::addNode($workflow, 'sales.report.deliver', [
    'recipient_list' => 'leadership',
], 'Deliver report');

Workflow::connect($schedule, $fetch);
Workflow::connect($fetch, $filter);
Workflow::connect($filter, $deliver);
Workflow::activate($workflow);
```

`recipient_list` is an opaque, host-authorized reference. It is not an email address or credential stored by Aitumalow.

Register Durable's tick command:

```php
Schedule::command('workflow:v2:schedule-tick')->everyMinute();
```

Durable owns the occurrence, overlap check, activity retries, history, and recovery. The host actions own sales access, tenancy, idempotency, and delivery.
