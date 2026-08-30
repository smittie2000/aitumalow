# Delay

`core.delay` pauses a graph using a Durable Workflow timer. No PHP process sleeps and Aitumalow dispatches no resume job.

```php
$delay = $workflow->addNode('Wait three days', 'core.delay', [
    'delay_type' => 'hours',
    'delay_value' => 72,
]);
```

The timer is recorded in Durable history. A worker can stop, deploy, or restart while the wait is pending; Durable resumes the graph when the timer is ready.
