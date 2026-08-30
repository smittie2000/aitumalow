<div v-pre>

# IF Condition

Evaluates each item against a condition and routes it to either the `true` or `false` output port.

**Node key:** `if_condition` · **Type:** Condition

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `field` | string | Yes | Yes | Field to evaluate |
| `operator` | select | Yes | No | Comparison operator (see below) |
| `value` | string | No | Yes | Value to compare against |

### Available Operators

| Operator | Description | Requires `value` |
| --- | --- | --- |
| `equals` | Field equals value | Yes |
| `not_equals` | Field does not equal value | Yes |
| `contains` | Field contains substring | Yes |
| `not_contains` | Field does not contain substring | Yes |
| `greater_than` | Field > value | Yes |
| `less_than` | Field < value | Yes |
| `greater_or_equal` | Field >= value | Yes |
| `less_or_equal` | Field <= value | Yes |
| `is_empty` | Field is null, empty, or missing | No |
| `is_not_empty` | Field has a value | No |
| `starts_with` | Field starts with value | Yes |
| `ends_with` | Field ends with value | Yes |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to evaluate |
| Output | `true` | Items that pass the condition |
| Output | `false` | Items that fail the condition |

## Behavior

Each item is evaluated independently:

1. Resolves the `field` expression against the current item
2. Applies the `operator`, comparing with `value` (also expression-resolved)
3. Items go to `true` or `false` port — never both, never duplicated

```text
Input: [{total: 750}, {total: 100}, {total: 500}]
       (operator: greater_than, value: 500)
          │
          ├─ true:  [{total: 750}]
          └─ false: [{total: 100}, {total: 500}]
```

Both output ports are optional — connect only what you need.

## Example

```php
$check = $workflow->addNode('Is VIP?', 'core.if_condition', [
    'field'    => '{{ item.total }}',
    'operator' => 'greater_than',
    'value'    => '500',
]);

$vipEmail = $workflow->addNode('VIP Email', 'app.vip.notify', [...]);
$stdEmail = $workflow->addNode('Standard Email', 'app.standard.notify', [...]);

$check->connect($vipEmail, 'true');
$check->connect($stdEmail, 'false');
```

## Input / Output Example

**Input:**

```php
[
    ['name' => 'Alice', 'total' => 750],
    ['name' => 'Bob',   'total' => 200],
]
```

**Config:** `field: "{{ item.total }}"`, `operator: "greater_than"`, `value: "500"`

**Output on `true`:**

```php
[
    ['name' => 'Alice', 'total' => 750],
]
```

**Output on `false`:**

```php
[
    ['name' => 'Bob', 'total' => 200],
]
```

## Tips

- Both output ports are optional — connect only `true` to silently drop non-matching items, or only `false` for the negative case
- Supports dot notation: `{{ item.address.country }}`
- `is_empty` / `is_not_empty` don't require a `value` — they check for null, empty string, or missing field


</div>
