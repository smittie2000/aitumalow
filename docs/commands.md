# Commands

## `workflow:validate`

Validate an Aitumalow graph before activation:

```bash
php artisan workflow:validate 42
```

## Durable runtime commands

Execution, workers, repair, retention, and schedule ticking belong to `durable-workflow/workflow`. In particular, register its schedule tick command:

```php
Schedule::command('workflow:v2:schedule-tick')->everyMinute();
```

Aitumalow intentionally provides no scheduler dispatcher, queue execution job, watchdog, or run-retention engine of its own.
