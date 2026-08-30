# Aitumalow

Aitumalow is a Laravel-native visual workflow builder for Filament applications,
powered by Durable Workflow v2.

The package is being built so a host application can register safe business
capabilities as workflow nodes, compose those nodes visually, capture immutable
run snapshots, and inspect durable executions from a Filament panel.

## Status

Aitumalow is under active development and is not yet declared production ready.
Runtime correctness is delegated to the pinned `durable-workflow/workflow` v2
dependency. The package itself owns the editor, stable host-capability catalog,
graph validation, scoped references, graph snapshots, and run projections.

## Principles

- Durable Workflow owns history, tasks, retries, timers, signals, schedules,
  cancellation, leases, and recovery.
- Laravel hosts own authorization, policies, tenancy, credentials, and business actions.
- Filament owns workflow administration and run inspection, not execution semantics.
- Host applications register subjects, nodes, triggers, and authorization adapters.
- Stored workflow definitions reference stable namespaced keys such as `app.ticket.create`, never arbitrary PHP classes or executable code.
- Every durable run receives an immutable serialized graph snapshot.
- The graph projection is shared by draft editing, version review, and run visualization.

## Package entry points

The Composer package is named `aitumalow/aitumalow` and uses the `Aitumalow\`
namespace. Laravel discovers `AitumalowServiceProvider` automatically.

Register the Filament plugin on each panel that should expose Aitumalow:

```php
use Aitumalow\AitumalowPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(AitumalowPlugin::make());
}
```

The package does not claim a `/workflow-editor` route. Embed the editor in a
host-owned Filament or Blade page so the application retains control of routing
and authorization:

```php
use Aitumalow\Http\EditorApiRoutes;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/aitumalow')
    ->middleware(['web', 'auth'])
    ->name('aitumalow.')
    ->group(fn () => EditorApiRoutes::register());
```

```blade
<x-aitumalow::editor
    :workflow-id="$workflow->getKey()"
    :api-base-url="url('/internal/aitumalow')"
/>
```

No HTTP routes, including public webhooks, are registered merely by installing
the package. External ingress is implemented by host-registered trigger
capabilities with provider-appropriate authentication and tenancy policy.

The compiled editor also exposes an injected JavaScript SDK and mount API for
non-Blade integrations. `sdk.catalog.list()` returns the same safe capability
metadata used by the embedded palette, so host triggers such as
`support.conversation.received` and actions such as `support.ticket.create`
appear without rebuilding the editor.

Host actions are ordinary container-resolved classes. The stable key is the
persisted API identity; presentation metadata and a package-neutral form schema
flow through the same catalog to the SDK and editor:

```php
use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\WorkflowContext;
use Aitumalow\Facades\WorkflowAutomation;

#[WorkflowNode(
    key: 'billing.payment.capture',
    name: 'Capture Payment',
    category: 'Billing',
    icon: 'credit-card',
)]
final class ProcessPaymentAction implements WorkflowAction
{
    public function schema(): array
    {
        return [
            ['key' => 'gateway', 'type' => 'string', 'label' => 'Gateway', 'required' => true],
            ['key' => 'amount', 'type' => 'string', 'label' => 'Amount', 'placeholder' => '{{ item.order.total }}'],
        ];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'transaction_id', 'type' => 'string', 'label' => 'Transaction ID'],
            ['key' => 'status', 'type' => 'string', 'label' => 'Status'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        $order = $context->get('order');

        return ['transaction_id' => 'tx_'.$order['id'], 'status' => 'paid'];
    }
}

WorkflowAutomation::register(ProcessPaymentAction::class);
```

There are no key aliases or class-name-derived identifiers. Renaming the PHP
class, display name, category, or icon does not change existing workflows.

The package ships no generic mail, HTTP, model-update, shell, arbitrary-code,
job-dispatch, notification, or provider node. Hosts register bounded equivalents
when their product needs them.

Once the package is published to Packagist, installation will use:

```bash
composer require aitumalow/aitumalow
php artisan vendor:publish --tag=aitumalow-config
php artisan migrate
```

Configure an asynchronous Laravel queue connection and the Durable Workflow v2
workers. For scheduled graphs, register
`workflow:v2:schedule-tick` in the host scheduler.

The package registers its migrations automatically. Publishing them with
`--tag=aitumalow-migrations` is optional and only needed when the host must
customize the schema.

## Architecture

See [docs/architecture.md](docs/architecture.md) for the governing package
boundary and delivery slices.

## Development

```bash
composer install
composer test
composer format:test
```

## License

Aitumalow is open-source software licensed under the [MIT License](LICENSE).
