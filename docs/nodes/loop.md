<div v-pre>

# Loop

Expands an array field within each item into individual items for per-element processing.

**Node key:** `loop` · **Type:** Control

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `source_field` | string | Yes | Yes | Array field to iterate over |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items containing arrays to expand |
| Output | `loop_item` | One item per array element |
| Output | `loop_done` | Original items after all elements are emitted |

## Behavior

For each input item, the node reads the array at `source_field` and emits one item per element on `loop_item` with:

- `_loop_item` — the current array element
- `_loop_index` — zero-based index
- `_loop_parent` — reference to the original parent item

After all elements are emitted, the original item is forwarded to `loop_done`.

```text
Input: [{order_id: 1, items: ["A", "B", "C"]}]
       (source_field: "items")

loop_item port:
  [{_loop_item: "A", _loop_index: 0, _loop_parent: {order_id: 1, ...}}]
  [{_loop_item: "B", _loop_index: 1, _loop_parent: {order_id: 1, ...}}]
  [{_loop_item: "C", _loop_index: 2, _loop_parent: {order_id: 1, ...}}]

loop_done port:
  [{order_id: 1, items: ["A", "B", "C"]}]
```

## Example

```php
$loop = $workflow->addNode('Each Line Item', 'core.loop', [
    'source_field' => 'line_items',
]);

$updateStock = $workflow->addNode('Update Stock', 'inventory.stock.update', [
    'model'      => 'App\\Models\\Product',
    'find_by'    => 'id',
    'find_value' => '{{ item._loop_item.product_id }}',
    'fields'     => ['stock' => '{{ item._loop_item.new_stock }}'],
]);

$summary = $workflow->addNode('Send Summary', 'inventory.summary.send', [...]);

$loop->connect($updateStock, 'loop_item');
$loop->connect($summary, 'loop_done');
```

## Tips

- Access loop data: `{{ item._loop_item.sku }}`, `{{ item._loop_parent.order_id }}`
- `loop_done` fires after all elements, ideal for summary or cleanup steps
- If `source_field` is not an array, the item goes straight to `loop_done` with no emissions


</div>
