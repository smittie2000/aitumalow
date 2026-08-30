# Merge

Collects items from multiple input ports and combines them into a single stream.

**Node key:** `merge` · **Type:** Control

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `mode` | select | No | No | Strategy: `append` (default), `zip`, or `wait_all` |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main_1` | First input stream |
| Input | `main_2` | Second input stream |
| Input | `main_3` | Third input stream |
| Input | `main_4` | Fourth input stream |
| Output | `main` | Combined output |

## Behavior

The engine automatically waits until all **connected** input ports have data before executing.

| Mode | Behavior |
| --- | --- |
| `append` | Concatenates items from all ports (port order: `main_1` first) |
| `zip` | Pairs items by index; shorter side padded with `null` |
| `wait_all` | Same as `append`, but explicitly declares "wait for all" intent |

Unconnected ports are ignored and do not block execution.

## Example

```php
$merge = $workflow->addNode('Combine', 'merge', ['mode' => 'append']);

$apiUsers->connect($merge, 'main', 'main_1');
$apiOrders->connect($merge, 'main', 'main_2');
```

## Input / Output Example

**Input on `main_1`:** `[{source: "users", name: "Alice"}]`

**Input on `main_2`:** `[{source: "orders", id: 42}]`

**Output (`append` mode):**

```php
[
    ['source' => 'users', 'name' => 'Alice'],
    ['source' => 'orders', 'id' => 42],
]
```

## Tips

- The engine handles synchronization automatically — no manual wait logic needed
- Use 2, 3, or 4 ports as needed; unconnected ports are skipped
- Items are concatenated in port order for predictable downstream processing
