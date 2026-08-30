# Filter

Evaluates conditions against each item and keeps only those that match. Unmatched items are discarded.

**Node key:** `filter` · **Type:** Utility

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `conditions` | array_of_objects | Yes | No | Filter conditions |
| `logic` | select | No | No | `and` (default) or `or` |

### Condition Definition

| Key | Type | Description |
| --- | --- | --- |
| `field` | string | Field name to check |
| `operator` | select | Any of the 12 comparison operators |
| `value` | string | Value to compare against |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to filter |
| Output | `main` | Items that passed the conditions |

## Behavior

- **`and` logic** — ALL conditions must pass
- **`or` logic** — ANY single condition passing is enough

Unmatched items are silently discarded — no `false` or `error` port.

```text
Input: [{revenue: 100}, {revenue: 0}, {revenue: 250}]
       (condition: revenue > 0)

Output: [{revenue: 100}, {revenue: 250}]
Dropped: [{revenue: 0}]
```

## Example

```php
$filter = $workflow->addNode('Active High-Value', 'core.filter', [
    'conditions' => [
        ['field' => 'status',  'operator' => 'equals',       'value' => 'active'],
        ['field' => 'revenue', 'operator' => 'greater_than', 'value' => '100'],
    ],
    'logic' => 'and',
]);
```

## Input / Output Example

**Input:**

```php
[
    ['name' => 'Alice', 'status' => 'active', 'revenue' => 500],
    ['name' => 'Bob',   'status' => 'inactive', 'revenue' => 300],
    ['name' => 'Carol', 'status' => 'active', 'revenue' => 50],
]
```

**Output (with `and` logic):**

```php
[
    ['name' => 'Alice', 'status' => 'active', 'revenue' => 500],
]
```

## Tips

- Unlike [IF Condition](/nodes/if-condition), filter has no `false` port — items are simply removed
- Use `or` logic for inclusive filtering: "keep items matching any criterion"
- All 12 operators are supported: `equals`, `not_equals`, `contains`, `not_contains`, `greater_than`, `less_than`, `greater_or_equal`, `less_or_equal`, `is_empty`, `is_not_empty`, `starts_with`, `ends_with`
