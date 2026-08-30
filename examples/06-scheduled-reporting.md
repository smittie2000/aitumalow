# Scheduled reporting with host capabilities

Register bounded host actions such as `sales.daily.fetch` and `sales.report.deliver`, then compose them with the package schedule trigger:

```php
$workflow = Workflow::create(['name' => 'Daily Sales Report']);

$schedule = Workflow::addNode($workflow, 'core.schedule', [
    'cron' => '0 8 * * *',
    'timezone' => 'UTC',
]);
$fetch = Workflow::addNode($workflow, 'sales.daily.fetch');
$deliver = Workflow::addNode($workflow, 'sales.report.deliver', [
    'recipient_list' => 'leadership',
]);

Workflow::connect($schedule, $fetch);
Workflow::connect($fetch, $deliver);
Workflow::activate($workflow);
```

The host action classes resolve data and recipients under the current `ExecutionScope`; the graph stores only stable keys and opaque references.

```php
Schedule::command('workflow:v2:schedule-tick')->everyMinute();
```

Durable Workflow owns occurrence history, overlap prevention, retries, and recovery. Aitumalow owns the visual composition and read projections.
