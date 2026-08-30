# Schedule trigger

`core.schedule` is a visual adapter for Durable Workflow v2 schedules.

```php
$schedule = $workflow->addNode('Every five minutes', 'core.schedule', [
    'cron' => '*/5 * * * *',
    'timezone' => 'UTC',
]);
```

Activating the workflow validates the graph, captures its snapshot, and creates or updates `aitumalow.workflow.{id}` through Durable's `ScheduleManager`. Deactivating the workflow pauses that schedule. Reactivating it updates the snapshot and resumes it.

Durable Workflow owns occurrence history, overlap protection, next-fire calculation, retries, and recovery. Aitumalow does not maintain a second occurrence table or scheduler command.

Run Durable's schedule tick command from the host scheduler:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('workflow:v2:schedule-tick')->everyMinute();
```

The trigger emits the stable Durable schedule id on `main`. Use host capability activities for report generation, delivery, or any other business effect.
