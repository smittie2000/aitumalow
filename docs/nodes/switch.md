# Switch

Routes each item to a named output port based on the first matching case. Multi-way branching.

**Node key:** `switch` · **Type:** Condition

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `field` | string | Yes | Yes | Field to check on each item |
| `cases` | array_of_objects | Yes | No | Ordered list of case definitions |
| `fallthrough` | boolean | No | No | Route unmatched items to `default` port (default: `true`) |

### Case Definition

| Key | Type | Description |
| --- | --- | --- |
| `port` | string | Output port name (e.g. `case_premium`) |
| `operator` | select | Comparison operator |
| `value` | string | Value to compare against |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to evaluate |
| Output | `default` | Items matching no case (when `fallthrough: true`) |
| Output | `case_*` | Dynamic ports defined by `cases` config |

## Behavior

For each item, cases are evaluated **in order** — first match wins:

```text
Input: [{plan: "enterprise"}, {plan: "free"}, {plan: "unknown"}]

  case_enterprise ← [{plan: "enterprise"}]
  case_free       ← [{plan: "free"}]
  default         ← [{plan: "unknown"}]
```

- Each item goes to exactly one port — no duplication
- If no case matches and `fallthrough: false`, the item is dropped

## Example

```php
$router = $workflow->addNode('Route by Plan', 'switch', [
    'field' => '{{ item.plan }}',
    'cases' => [
        ['port' => 'case_enterprise', 'operator' => 'equals', 'value' => 'enterprise'],
        ['port' => 'case_pro',        'operator' => 'equals', 'value' => 'pro'],
        ['port' => 'case_free',       'operator' => 'equals', 'value' => 'free'],
    ],
    'fallthrough' => true,
]);

$router->connect($enterpriseFlow, 'case_enterprise');
$router->connect($proFlow,        'case_pro');
$router->connect($freeFlow,       'case_free');
$router->connect($unknownFlow,    'default');
```

## Tips

- Port names are arbitrary strings — use descriptive names like `case_premium` or `case_us_region`
- Cases are evaluated in order: place more specific conditions before general ones
- Set `fallthrough: false` to silently drop unmatched items instead of routing to `default`
