<div v-pre>

# Custom Nodes

Create your own node types to extend the workflow engine with custom logic.

## Creating an Action

Most host capabilities need only the `WorkflowAction` contract. The schema is
package-neutral metadata consumed by the SDK and embedded editor:

```php
<?php

namespace App\Actions;

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\DTOs\WorkflowContext;

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
            ['key' => 'amount', 'type' => 'string', 'label' => 'Amount', 'supports_expression' => true],
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

        return ['transaction_id' => 'tx_123', 'status' => 'paid'];
    }
}
```

The host registers this class explicitly. Laravel's container constructs the
action, so constructor-injected application services continue to work.

## The WorkflowNode Attribute

```php
#[WorkflowNode(
    key: 'billing.payment.capture', // Stable persisted identifier
    name: 'Capture Payment',        // Human-readable name
    category: 'Billing',            // Palette group
    icon: 'credit-card',            // SDK icon hint
)]
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `key` | string | Stable namespaced identifier persisted in workflows |
| `name` | string | Human-readable display name |
| `category` | string | Palette group |
| `icon` | string | Optional SDK icon hint |
| `description` | string | Optional help text |
| `type` | NodeType | Optional for actions; required by advanced node kinds |

## Advanced NodeInterface

Triggers, conditions, multi-port nodes, and controls use `NodeInterface`:

```php
interface NodeInterface
{
    public function inputPorts(): array;    // e.g. ['main']
    public function outputPorts(): array;   // e.g. ['main', 'error']
    public static function configSchema(): array;
    public static function outputSchema(): array;
    public function execute(NodeInput $input, array $config): NodeOutput;
}
```

The `BaseNode` class provides sensible defaults: input `['main']`, output `['main', 'error']`, an empty config schema, and an empty output schema.

## NodeInput

```php
class NodeInput
{
    public readonly array $items;              // Array of items to process
    public readonly ExecutionContext $context;  // Run context (IDs, outputs)
}
```

## NodeOutput

Create output using static methods:

```php
// Send all items to the 'main' port
NodeOutput::main($items);

// Send items to a specific port
NodeOutput::port('custom_port', $items);

// Send items to multiple ports
NodeOutput::ports([
    'main'  => $successItems,
    'error' => $errorItems,
]);
```

## Output Schema

The output schema declares what variables a node produces. This powers the visual editor's autocomplete and variable panel, helping users discover available variables when writing expressions. `WorkflowAction::outputSchema()` returns fields for its `main` port; advanced `NodeInterface` implementations return fields grouped by port.

Each key in the returned array is a port name, and the value is an array of field definitions:

```php
public static function outputSchema(): array
{
    return [
        'main' => [
            ['key' => 'slack_sent', 'type' => 'boolean', 'label' => 'Slack Sent'],
            ['key' => 'channel', 'type' => 'string', 'label' => 'Channel Name'],
        ],
    ];
}
```

Downstream nodes will see these as `{{ nodes.Slack Message.main.0.slack_sent }}` in the autocomplete.

**Field definition:**

| Property | Type | Description |
| --- | --- | --- |
| `key` | string | Dot-notation path of the output field |
| `type` | string | Data type: `string`, `integer`, `boolean`, `object`, `array`, `mixed` |
| `label` | string | Human-readable label shown in the editor |

For nodes with dynamic output (e.g. Set Fields), you can return a wildcard marker:

```php
return [
    'main' => [
        ['key' => '*', 'type' => 'mixed', 'label' => 'Dynamic fields from config'],
    ],
];
```

`BaseNode` returns an empty output schema by default. Override it in your node to enable variable discovery.

## Config Schema

The config schema defines what fields appear in the visual editor and validates configuration:

```php
public static function configSchema(): array
{
    return [
        [
            'key'                 => 'field_name',
            'type'                => 'string',
            'label'               => 'Display Label',
            'required'            => true,
            'supports_expression' => true,
            'description'         => 'Help text below the field',
            'placeholder'         => 'Placeholder text',
        ],
    ];
}
```

### Field Types

| Type | Description | Extra Properties |
|------|-------------|------------------|
| `string` | Single-line text input | `placeholder`, `supports_expression` |
| `reference` | Host-provided opaque resource selector | `source` |
| `textarea` | Multi-line text input | `placeholder`, `supports_expression` |
| `integer` | Integer number input | `placeholder` |
| `number` | Float number input | `min`, `max`, `step`, `placeholder` |
| `boolean` | Toggle switch | — |
| `select` | Dropdown | `options`, `depends_on`, `options_map` |
| `multiselect` | Multi-selection | `options` |
| `json` | JSON editor with validation | — |
| `keyvalue` | Dynamic key-value pairs | — |
| `array_of_objects` | Repeatable nested groups | `schema` (nested field definitions) |
| `reference` | Host-scoped opaque reference picker | `source` |
| `url` | URL input with validation | `placeholder`, `supports_expression` |
| `color` | Color picker with hex input | `placeholder` |
| `slider` | Range slider | `min`, `max`, `step` |
| `code` | Monospace code editor | `language`, `placeholder`, `supports_expression` |
| `info` | Read-only information text (not a form field) | `description` |
| `section` | Collapsible section heading | `collapsible`, `collapsed` |
| `custom` | Web Component (see [Plugin System](/advanced/plugins)) | `custom_component` |

### Field Properties

| Property | Type | Description |
|----------|------|-------------|
| `key` | string | Field identifier, used as the config key |
| `type` | string | One of the field types above |
| `label` | string | Display label |
| `required` | boolean | Whether the field is required |
| `supports_expression` | boolean | Allow `{{ }}` template syntax |
| `description` | string | Help text shown below the field |
| `placeholder` | string | Input placeholder text |
| `options` | string[] | Static options for `select` / `multiselect` |
| `depends_on` | string | Key of parent field for dynamic options |
| `options_map` | Record | Options per parent value: `{'parent_value': ['opt1', 'opt2']}` |
| `show_when` | object | Conditional visibility: `{'key': 'field', 'value': 'expected'}` |
| `schema` | array | Nested field definitions for `array_of_objects` |
| `min` / `max` / `step` | number | For `number`, `slider` types |
| `language` | string | Language hint for `code` type |
| `collapsible` / `collapsed` | boolean | For `section` type |
| `readonly` | boolean | Disable editing |

### Sections and Layout

Group related fields with `section`:

```php
public static function configSchema(): array
{
    return [
        ['key' => '_settings', 'type' => 'section', 'label' => 'Settings', 'collapsible' => true],
        ['key' => 'region', 'type' => 'select', 'label' => 'Region', 'options' => ['africa', 'europe']],
        ['key' => 'color', 'type' => 'color', 'label' => 'Brand Color'],
        ['key' => 'rate', 'type' => 'slider', 'label' => 'Rate Limit', 'min' => 1, 'max' => 100, 'step' => 1],
    ];
}
```

Configuration is persisted with the workflow. Do not declare API keys, tokens,
passwords, or other secrets as schema fields. Resolve authenticated provider
clients inside a host-owned action instead.

### Dependent Select

Make a select's options change based on another field:

```php
['key' => 'channel', 'type' => 'select', 'label' => 'Channel', 'options' => ['email', 'sms']],
['key' => 'template', 'type' => 'select', 'label' => 'Template', 'depends_on' => 'channel', 'options_map' => [
    'email' => ['welcome-email', 'receipt-email'],
    'sms'   => ['verification-sms', 'reminder-sms'],
]],
```

### Conditional Visibility

Show/hide fields based on other field values:

```php
['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => ['inline', 'template']],
['key' => 'body', 'type' => 'textarea', 'label' => 'Body', 'show_when' => ['key' => 'mode', 'value' => 'inline']],
['key' => 'template_id', 'type' => 'string', 'label' => 'Template ID', 'show_when' => ['key' => 'mode', 'value' => 'template']],
```

## Registering Custom Nodes

Capabilities are explicit. Give every host capability a lowercase namespaced
key and register each class from a host service provider:

```php
use Aitumalow\Facades\WorkflowAutomation;

WorkflowAutomation::register(WhatsAppConversationStarted::class);
WorkflowAutomation::register(CreateTicket::class);
```

Keys such as `app.whatsapp.conversation_started` and `app.ticket.create` are
persisted in workflow definitions and returned by the catalog. Invalid or
duplicate keys fail during registration. Executable PHP class names are never
returned by the catalog.

## Creating a Trigger

Triggers implement `TriggerInterface` instead of `NodeInterface`:

```php
use Aitumalow\Contracts\TriggerInterface;

#[WorkflowNode(key: 'app.my_trigger', name: 'My Trigger', category: 'Triggers', type: NodeType::Trigger)]
class MyTrigger implements TriggerInterface
{
    public function inputPorts(): array { return []; }      // Triggers have no input
    public function outputPorts(): array { return ['main']; }

    public static function configSchema(): array { return []; }

    public static function outputSchema(): array { return []; }

    public function register(int $workflowId, int $nodeId, array $config): void
    {
        // Called when the workflow is activated
    }

    public function unregister(int $workflowId, int $nodeId, array $config): void
    {
        // Called when the workflow is deactivated
    }

    public function extractPayload(mixed $event): array
    {
        // Convert the triggering event to an items array
        return is_array($event) ? $event : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
```

## Using Your Custom Node

```php
$workflow = Workflow::create(['name' => 'Alert Pipeline']);

$trigger = $workflow->addNode('New Alert', 'core.manual');
$slack   = $workflow->addNode('Notify Team', 'acme.slack.send_message', [
    'channel'     => '#alerts',
    'message'     => 'Alert: {{ item.message }}',
]);

$trigger->connect($slack);
$workflow->activate();
```

## Node Documentation

Add documentation that appears in the visual editor's **Docs** tab when users select your node. Override the `documentation()` method on your node class:

```php
#[WorkflowNode(key: 'acme.slack.send_message', name: 'Slack Message', category: 'Slack', type: NodeType::Action)]
class SlackMessageNode extends BaseNode
{
    public static function documentation(): ?string
    {
        return file_get_contents(__DIR__.'/../docs/slack-message.md');
    }
}
```

You can also return inline markdown:

```php
public static function documentation(): ?string
{
    return <<<'MD'
    # Slack Message

    Sends a message to a Slack channel via webhook.

    ## Config

    | Key | Type | Required | Description |
    |-----|------|----------|-------------|
    | channel | string | Yes | Target channel name |
    | message | textarea | Yes | Message body (supports expressions) |

    ## Tips

    - Use `{{ }}` expressions in the message field to include dynamic data
    - The node outputs `slack_sent: true` on success
    MD;
}
```

`BaseNode` provides a default implementation that automatically loads the matching markdown file from the package's `docs/` directory (e.g. `docs/nodes/slack-message.md` for key `acme.slack.send_message`). If no file exists, it returns `null` and the Docs tab is hidden.

## Dependency Injection

Custom nodes support constructor injection from the Laravel container:

```php
#[WorkflowNode(key: 'acme.risk.classify', name: 'Risk Classify', category: 'Risk', type: NodeType::Action)]
class RiskClassifyNode extends BaseNode
{
    public function __construct(
        private readonly RiskClassificationService $classifier,
    ) {}

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];
        foreach ($input->items as $item) {
            $category = $this->classifier->classify($item);
            $results[] = array_merge($item, ['category' => $category]);
        }
        return NodeOutput::main($results);
    }
}
```

</div>
