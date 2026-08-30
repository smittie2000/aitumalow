<div v-pre>

# Parse Data

Parses raw data from a field (JSON, CSV, or query string) into structured data.

**Node key:** `parse_data` · **Type:** Transformer

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `source_field` | string | Yes | Yes | Field containing the raw data |
| `format` | select | Yes | No | Parse format: `json`, `csv`, or `key_value` |
| `target_field` | string | Yes | No | Field to store the parsed result |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items with parsed data added |
| Output | `error` | Items that failed to parse |

## Parse Formats

| Format | Behavior |
| --- | --- |
| `json` | `json_decode()` — produces array or scalar |
| `csv` | First row = headers, subsequent rows = associative arrays |
| `key_value` | `parse_str()` — handles `key1=val1&key2=val2` format |

## Examples

### JSON — Parse an API response

A host-registered integration action fetches order data from an external API. Its response body comes back as a raw JSON string — Parse Data turns it into a usable array.

```php
$fetch = $workflow->addNode('Fetch Order', 'commerce.order.fetch', [
    'url'    => 'https://api.store.com/orders/{{ item.order_id }}',
    'method' => 'GET',
]);

$parse = $workflow->addNode('Parse Response', 'parse_data', [
    'source_field' => 'response_body',   // raw JSON string from the HTTP node
    'format'       => 'json',
    'target_field' => 'order',           // parsed array available as {{ item.order }}
]);

$notify = $workflow->addNode('Notify Customer', 'commerce.customer.notify', [
    'to'      => '{{ item.order.customer.email }}',
    'subject' => 'Your order #{{ item.order.id }} has shipped!',
]);

$fetch->connect($parse);
$parse->connect($notify);
```

**Before parse** — `response_body` is a string:

```json
"{\"id\":1024,\"customer\":{\"email\":\"john@example.com\"},\"status\":\"shipped\"}"
```

**After parse** — `order` is a structured array you can reference with expressions:

```json
{
  "order": {
    "id": 1024,
    "customer": { "email": "john@example.com" },
    "status": "shipped"
  }
}
```

### CSV — Import contacts from a file

A host trigger supplies CSV data. Parse Data splits it into rows, then a Loop node processes each contact.

```php
$trigger = $workflow->addNode('CSV Import', 'core.manual', []);

$parse = $workflow->addNode('Parse CSV', 'parse_data', [
    'source_field' => 'csv_body',
    'format'       => 'csv',
    'target_field' => 'contacts',
]);

$loop = $workflow->addNode('Each Contact', 'core.loop', [
    'source_field' => 'contacts',
]);

$mail = $workflow->addNode('Send Welcome', 'app.welcome.send', [
    'to'      => '{{ item.email }}',
    'subject' => 'Welcome, {{ item.name }}!',
]);

$trigger->connect($parse);
$parse->connect($loop);
$loop->connect($mail);
```

**Before parse** — `csv_body` is a raw string:

```
name,email,role
Alice,alice@example.com,admin
Bob,bob@example.com,editor
```

**After parse** — `contacts` is an array of rows:

```json
{
  "contacts": [
    { "name": "Alice", "email": "alice@example.com", "role": "admin" },
    { "name": "Bob", "email": "bob@example.com", "role": "editor" }
  ]
}
```

### Query String — Parse form data

A host trigger supplies URL-encoded form data. Parse Data converts it into key-value pairs.

```php
$trigger = $workflow->addNode('Form Submit', 'core.manual', []);

$parse = $workflow->addNode('Parse Form', 'parse_data', [
    'source_field' => 'body',
    'format'       => 'key_value',
    'target_field' => 'form',
]);

$trigger->connect($parse);
```

**Before parse** — `body` is a query string:

```
name=Alice&email=alice%40example.com&plan=pro
```

**After parse** — `form` is a structured array:

```json
{
  "form": {
    "name": "Alice",
    "email": "alice@example.com",
    "plan": "pro"
  }
}
```

## Tips

- Combine with a [Loop](/nodes/loop) node to iterate over parsed CSV rows or JSON arrays
- The `source_field` supports expressions — use `{{ nodes.Fetch Orders.main.0.response_body }}` to reference a previous node's output
- Malformed data routes to the `error` port with the exception message, so you can handle failures gracefully

</div>
