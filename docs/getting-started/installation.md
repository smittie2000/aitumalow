# Installation

## Requirements

- PHP 8.4+
- Laravel 13

## Install via Composer

```bash
composer require aitumalow/aitumalow
```

The package auto-discovers its service provider. No manual registration needed.

## Publish Configuration

```bash
php artisan vendor:publish --provider="Aitumalow\AitumalowServiceProvider"
```

This creates `config/aitumalow.php` where you can customize table names, queue settings, and runtime limits.

## Run Migrations

The package registers its migrations with the host application automatically;
they do not need to be published first.

```bash
php artisan migrate
```

Durable Workflow owns a separate set of runtime migrations. The same command
applies both packages' pending migrations. Before enabling workflow traffic in
an upgraded environment, run its readiness check:

```bash
php artisan workflow:v2:doctor --strict
```

The ten Aitumalow migrations create eleven package-owned tables:

| Table | Purpose |
|-------|---------|
| `workflows` | Workflow definitions |
| `workflow_nodes` | Nodes within each workflow |
| `workflow_edges` | Connections between nodes |
| `workflow_graph_edits` | Atomic draft edit receipts for retries and undo/redo |
| `workflow_revisions` | Immutable published workflow versions |
| `workflow_runs` | Execution records |
| `workflow_commands` | Idempotent commands sent to waiting runs |
| `workflow_node_runs` | Per-node execution logs |
| `workflow_tags` | Tags for categorizing workflows |
| `workflow_tag_pivot` | Many-to-many workflow ↔ tag |
| `workflow_folders` | Hierarchical folder organization |

::: tip Customizing Table Names
You can change table names in the config **before** running migrations:

```php
'tables' => [
    'workflows'  => 'my_workflows',
    'nodes'      => 'my_workflow_nodes',
    // ...
],
```
:::

## Publish Migrations (Optional)

Only publish the migrations when the host application needs to modify them:

```bash
php artisan vendor:publish --tag=aitumalow-migrations
```

## Register the editor API (optional)

The package registers no HTTP routes by default. If the browser editor is used,
register its transport inside a host-owned authenticated route group:

```php
use Aitumalow\Http\EditorApiRoutes;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/aitumalow')
    ->middleware(['web', 'auth'])
    ->name('aitumalow.')
    ->group(fn () => EditorApiRoutes::register());
```

## Visual Editor

The editor is an embeddable component rather than a package-owned route. Publish
Filament assets, then place it in a host-owned Blade or Filament page:

```bash
php artisan filament:assets
```

```blade
<x-aitumalow::editor
    :workflow-id="$workflow->getKey()"
    :api-base-url="url('/internal/aitumalow')"
/>
```

See the [Workflow UI Editor](/ui-editor) docs for the SDK and customization options.

## Next Steps

Head to [Quick Start](/getting-started/quick-start) to build your first workflow in under 5 minutes.
