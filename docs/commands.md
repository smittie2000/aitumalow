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

After installing or upgrading the runtime, migrate and verify the host's
database, queue, cache, codec, and worker topology before serving workflow
traffic:

```bash
php artisan migrate
php artisan workflow:v2:doctor --strict
```

Use `--json` when the readiness result is consumed by deployment automation.
Hosts migrating pre-v2 Durable Workflow runs must also choose and verify the
dependency's explicit `drain` or `coexist` strategy; Composer updates do not
reinterpret v1 history as v2 history.

Aitumalow intentionally provides no scheduler dispatcher, queue execution job, watchdog, or run-retention engine of its own.
