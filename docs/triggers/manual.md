# Manual Trigger

`core.manual` is the pass-through entry point for a workflow started by the host,
the authorized HTTP API, or the optional MCP adapter.

It has no input port and emits the supplied list of items on `main`. Its optional
`input_schema` is descriptive configuration for the workflow author.

```php
use Aitumalow\Facades\Workflow;

$run = Workflow::run($workflow, [[
    'tenant_id' => 7,
    'ticket_id' => 42,
]]);

// The run is queued in Durable Workflow and returns immediately.
echo $run->durable_run_id;
```

The returned Aitumalow run is a projection. Configure an asynchronous Laravel
queue and run the Durable workers to execute it. Inspect or refresh the run to
observe `running`, `waiting`, or a terminal state.

For application events or webhooks, the host first authenticates the source,
resolves actor and tenant scope, converts the event into a bounded payload, and
then calls the same run boundary. Aitumalow does not auto-subscribe arbitrary
Eloquent model classes.

Use [Schedule](/triggers/schedule) for recurring time-based starts.
