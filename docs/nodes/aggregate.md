# Aggregate

Groups items and applies aggregate functions (sum, count, avg, min, max). Produces one output item per group.

**Node key:** `aggregate` · **Type:** Utility

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `group_by` | string | No | No | Field to group by (empty = all items as one group) |
| `operations` | array_of_objects | Yes | No | Aggregate operations |

### Operation Definition

| Key | Type | Description |
| --- | --- | --- |
| `field` | string | Field to aggregate |
| `function` | select | `sum`, `count`, `avg`, `min`, or `max` |
| `alias` | string | Output field name |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to aggregate |
| Output | `main` | One item per group with computed values |

## Behavior

```text
Input: [
  {dept: "Sales", revenue: 100},
  {dept: "Sales", revenue: 200},
  {dept: "Eng",   revenue: 150}
]

Config: group_by: "dept"
        operations: [{field: "revenue", function: "sum", alias: "total"}]

Output: [
  {dept: "Sales", total: 300},
  {dept: "Eng",   total: 150}
]
```

Leave `group_by` empty to produce a single summary item for all inputs.

## Example

```php
$aggregate = $workflow->addNode('Summarize', 'aggregate', [
    'group_by'   => 'department',
    'operations' => [
        ['field' => 'revenue', 'function' => 'sum',   'alias' => 'total_revenue'],
        ['field' => 'id',      'function' => 'count', 'alias' => 'transaction_count'],
        ['field' => 'revenue', 'function' => 'avg',   'alias' => 'avg_revenue'],
    ],
]);
```

## Input / Output Example

**Input:**

```php
[
    ['department' => 'Sales', 'revenue' => 100, 'id' => 1],
    ['department' => 'Sales', 'revenue' => 200, 'id' => 2],
    ['department' => 'Eng',   'revenue' => 150, 'id' => 3],
]
```

**Output:**

```php
[
    ['department' => 'Sales', 'total_revenue' => 300, 'transaction_count' => 2, 'avg_revenue' => 150],
    ['department' => 'Eng',   'total_revenue' => 150, 'transaction_count' => 1, 'avg_revenue' => 150],
]
```

## Tips

- Leave `group_by` empty to aggregate all items into a single summary
- Each group produces exactly one output item
- The `alias` becomes the key in the output — use descriptive names like `total_revenue`
