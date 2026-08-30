# Configuration

Publish `config/aitumalow.php` when the host needs custom table/model names or plugin registration.

```php
return [
    'tables' => [
        'workflows' => 'aitumalow_workflows',
        'nodes' => 'aitumalow_workflow_nodes',
        'edges' => 'aitumalow_workflow_edges',
        'runs' => 'aitumalow_workflow_runs',
        'node_runs' => 'aitumalow_workflow_node_runs',
        'tags' => 'aitumalow_workflow_tags',
        'tag_pivot' => 'aitumalow_workflow_tag_pivot',
        'folders' => 'aitumalow_workflow_folders',
    ],
    'queue' => env('AITUMALOW_QUEUE', 'default'),
    'default_retry_count' => 0,
    'default_retry_delay_ms' => 1000,
    'expression_mode' => 'safe',
    'plugins' => [],
];
```

The host configures Durable Workflow's database, queue, workers, schedules, history, repair, and retention through the dependency's `workflows.php` configuration. Use an asynchronous queue driver.

Aitumalow has no credentials, provider catalog, shell execution, HTTP action, model mutation, mail action, queue job action, or arbitrary-code configuration. Register those behaviors as explicit host capabilities when they are appropriate.
