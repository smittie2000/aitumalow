# Authorization

Aitumalow does not register HTTP routes or define an application authorization
gate. The host owns every HTTP boundary.

## Editor API

Register the optional editor transport inside the same authentication, tenancy,
and policy boundary as the host page that embeds it:

```php
use Aitumalow\Http\EditorApiRoutes;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/aitumalow')
    ->middleware([
        'web',
        'auth',
        App\Http\Middleware\ResolveTenant::class,
        App\Http\Middleware\AuthorizeWorkflowAdministration::class,
    ])
    ->name('aitumalow.')
    ->group(fn () => EditorApiRoutes::register());
```

The package route adapter supplies controller mappings and scoped nested model
bindings only. It does not choose an authentication guard, grant local-environment
bypasses, or infer the host's actor and tenant policies.

If the host uses a custom editor SDK transport, PHP-only workflows, queue
dispatch, or local MCP, it does not need to register the editor API.

## External triggers

Aitumalow does not ship a generic public webhook endpoint. A host that receives
GitHub, Stripe, or another provider webhook should own that route and:

1. verify the provider signature against the raw request body;
2. resolve the permitted tenant and trigger capability;
3. validate and project only the allowed workflow input; and
4. invoke the registered trigger through application-owned policy.

This keeps a public ingress route from bypassing the host's authentication,
tenancy, replay protection, rate limiting, and audit rules.
