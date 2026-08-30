# Plugin System

Build and distribute reusable workflow node packages. Third-party developers can create Composer packages that integrate seamlessly with the workflow engine — install via `composer require` and they work immediately.

## How It Works

The plugin system follows a Filament-inspired architecture:

1. Each plugin implements `PluginInterface` with `register()` and `boot()` lifecycle methods
2. Plugins register their nodes, expression functions, and middleware via `PluginContext`
3. Auto-discovery works through Laravel's standard package auto-discovery (`extra.laravel.providers`)

Plugins are application-lifetime definitions. Register them from a service
provider and keep plugin instances stateless; do not retain a request,
authenticated user, model, or other request-specific value on a plugin. Node
middleware is registered by class name so it can be resolved from the current
request or job container lifecycle.

## Using Plugins

### Install via Composer

```bash
composer require acme/workflow-slack
```

That's it. The plugin's nodes appear in the editor palette automatically.

### Register via Config

Alternatively, register plugins in the config file:

```php
// config/aitumalow.php
'plugins' => [
    \Acme\WorkflowSlack\SlackPlugin::class,
],
```

### Check Installed Plugins

```php
use Aitumalow\Facades\WorkflowAutomation;

$plugins = WorkflowAutomation::plugins()->all();

WorkflowAutomation::plugins()->has('acme/workflow-slack'); // true
```

## Creating a Plugin

### 1. Plugin Class

Create a class extending `BasePlugin`:

```php
<?php

namespace Acme\WorkflowSlack;

use Aitumalow\Plugin\BasePlugin;
use Aitumalow\Plugin\PluginContext;

class SlackPlugin extends BasePlugin
{
    public function getId(): string
    {
        return 'acme/workflow-slack';
    }

    public function getName(): string
    {
        return 'Slack Integration';
    }

    public function register(PluginContext $context): void
    {
        // Register individual node classes
        $context->registerNode(Nodes\SendSlackMessageAction::class);
        $context->registerNode(Nodes\SlackWebhookTrigger::class);

        // Register custom expression functions
        $context->registerExpressionFunction(
            'slack_escape',
            fn (string $text) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text),
        );
    }

    public function boot(PluginContext $context): void
    {
        // Register middleware (optional)
        $context->registerMiddleware(Middleware\SlackRateLimiter::class);
    }
}
```

### 2. Service Provider

Create a standard Laravel service provider that registers your plugin:

```php
<?php

namespace Acme\WorkflowSlack;

use Aitumalow\Facades\WorkflowAutomation;
use Illuminate\Support\ServiceProvider;

class SlackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        WorkflowAutomation::plugin(SlackPlugin::make());
    }
}
```

### 3. Node Classes

Create nodes using the standard `#[WorkflowNode]` attribute and `BaseNode` class:

```php
<?php

namespace Acme\WorkflowSlack\Nodes;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Enums\NodeType;
use Aitumalow\Nodes\BaseNode;

#[WorkflowNode(key: 'acme.slack.send_message', name: 'Send Slack Message', category: 'Slack', type: NodeType::Action)]
class SendSlackMessageAction extends BaseNode
{
    public function __construct(private readonly SlackClient $slack) {}

    public static function configSchema(): array
    {
        return [
            ['key' => 'channel', 'type' => 'string', 'label' => 'Channel', 'required' => true, 'supports_expression' => true],
            ['key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true, 'supports_expression' => true],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            $this->slack->send($config['channel'], $config['message']);

            $results[] = array_merge($item, ['slack_sent' => true]);
        }

        return NodeOutput::main($results);
    }
}
```

### 4. Composer Package Setup

Your `composer.json` should use Laravel's auto-discovery:

```json
{
    "name": "acme/workflow-slack",
    "require": {
        "aitumalow/aitumalow": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\WorkflowSlack\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Acme\\WorkflowSlack\\SlackServiceProvider"
            ]
        }
    }
}
```

### 5. Node Documentation

Add documentation that appears in the editor's **Docs** tab. Override `documentation()` on your node classes:

```php
#[WorkflowNode(key: 'acme.slack.send_message', name: 'Send Slack Message', category: 'Slack', type: NodeType::Action)]
class SendSlackMessageAction extends BaseNode
{
    public static function documentation(): ?string
    {
        return file_get_contents(__DIR__.'/../../docs/slack-send-message.md');
    }

    // ... configSchema(), execute(), etc.
}
```

You can also return inline markdown directly. If `documentation()` returns `null` (the default), the Docs tab is hidden for that node.

### Package Structure

```
acme/workflow-slack/
├── composer.json
├── docs/
│   ├── slack-send-message.md
│   └── slack-webhook-trigger.md
├── src/
│   ├── SlackPlugin.php
│   ├── SlackServiceProvider.php
│   ├── Middleware/
│   │   └── SlackRateLimiter.php
│   └── Nodes/
│       ├── SendSlackMessageAction.php
│       └── SlackWebhookTrigger.php
```

## PluginInterface

The full contract for plugins:

```php
interface PluginInterface
{
    public function getId(): string;
    public function getName(): string;
    public function register(PluginContext $context): void;
    public function boot(PluginContext $context): void;
    public function editorScripts(): array;
    public static function make(): static;
}
```

| Method | When Called | Purpose |
|--------|-----------|---------|
| `getId()` | Always | Unique plugin identifier |
| `getName()` | Always | Human-readable name |
| `register()` | During ServiceProvider `register()` | Register nodes, expression functions |
| `boot()` | During ServiceProvider `boot()` | Register routes, listeners, middleware |
| `editorScripts()` | When editor loads | Return JS asset URLs for custom config fields |
| `make()` | By consumer | Static factory for fluent API |

## PluginContext API

The `PluginContext` is the API surface plugins interact with:

| Method | Description |
|--------|-------------|
| `registerNode(string $class)` | Register one explicitly selected capability class with a stable namespaced `#[WorkflowNode]` key |
| `registerExpressionFunction(string $name, callable $fn)` | Add a custom expression function |
| `registerMiddleware(string $middleware)` | Add a container-resolved node middleware class |

## Node Middleware

Middleware wraps every node execution, allowing plugins to add logging, rate limiting, metrics, etc:

```php
<?php

namespace Acme\WorkflowSlack\Middleware;

use Aitumalow\Contracts\NodeInterface;
use Aitumalow\Contracts\NodeMiddlewareInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Closure;

class SlackRateLimiter implements NodeMiddlewareInterface
{
    public function handle(
        NodeInterface $node,
        NodeInput $input,
        array $config,
        Closure $next,
    ): NodeOutput {
        // Only apply to Slack nodes
        if (! $node instanceof \Acme\WorkflowSlack\Nodes\SendSlackMessageAction) {
            return $next($node, $input, $config);
        }

        // Rate limit logic...
        \Illuminate\Support\Facades\RateLimiter::attempt('slack', 30, function () {});

        return $next($node, $input, $config);
    }
}
```

Middleware runs in FIFO order and wraps the `$node->execute()` call. Each retry attempt also passes through the full middleware stack.

## Node Types

Plugin nodes must use one of the existing `NodeType` values:

| NodeType | Use When |
|----------|----------|
| `Trigger` | Your node starts workflows (implements `TriggerInterface`) |
| `Action` | Your node performs side effects (API calls, emails, etc.) |
| `Condition` | Your node routes flow based on logic |
| `Transformer` | Your node transforms data |
| `Control` | Your node controls flow (loops, delays, etc.) |
| `Utility` | General-purpose nodes |

## WorkflowAutomation Facade

```php
use Aitumalow\Facades\WorkflowAutomation;

// Register a plugin
WorkflowAutomation::plugin(SlackPlugin::make());

// Get plugin registry
WorkflowAutomation::plugins();         // PluginRegistry instance
WorkflowAutomation::plugins()->all();  // All registered plugins
WorkflowAutomation::plugins()->has('acme/workflow-slack'); // bool
WorkflowAutomation::plugins()->get('acme/workflow-slack'); // PluginInterface|null
```
