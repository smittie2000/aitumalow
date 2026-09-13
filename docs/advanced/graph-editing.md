# Graph editing API

The editor uses an identity-preserving graph API. `WorkflowGraphService` owns
atomic draft edits; `WorkflowService` continues to own publication and execution.
The host still mounts `EditorApiRoutes` behind its own authorization middleware.

## Upgrade an existing host

Run `php artisan migrate` after installing the package update. The new
`2026_09_13_000001_create_workflow_graph_edits_table.php` migration is additive;
existing graph and Durable tables are unchanged. Publish or refresh editor assets
using your normal Filament asset deployment so the bundle and API update together.
The new bundle requires these graph endpoints. Custom SDK objects must provide
`graph.get()` and `graph.edit()`; `createEditorSdk()` supplies them automatically.

The table and model remain configurable via `aitumalow.tables.graph_edits` and
`aitumalow.models.graph_edit`. Existing published config files use their defaults
until overridden. No additional Composer or npm dependency is required.

## Read and edit

`GET /workflows/{workflow}/graph` returns:

```json
{
  "data": {
    "workflow": { "id": 1, "nodes": [], "edges": [] },
    "hash": "64-character editor graph hash"
  }
}
```

`workflow` uses the usual resource, including metadata and live revision details.
The editor hash covers persisted nodes, edges, IDs, configuration, positions and
pins. It excludes workflow metadata, publication and run state. It is distinct
from the alias-based `WorkflowDraftService` hash and the published revision hash.

Send `POST /workflows/{workflow}/graph-edits` for one authoring gesture:

```json
{
  "request_id": "a6f4401c-6db3-4ac0-bf33-a3ab737a76b8",
  "expected_hash": "hash returned by the graph API",
  "operation": "add_node",
  "data": {
    "node_key": "core.delay",
    "name": "Wait for review",
    "config": {},
    "position_x": 400,
    "position_y": 450,
    "source": { "node_id": 12, "port": "true" },
    "input_port": "main"
  }
}
```

The response contains the current `workflow` and `hash`, plus an `edit` receipt:
`id`, `operation`, `before_hash`, `after_hash`, and nullable `created_node_id`.
Adding the node and its optional connection either both commit or both roll back.

| Operation | `data` fields |
| --- | --- |
| `add_node` | `node_key`, optional `name`/`config`, integer `position_x`/`position_y`. Optional `source: {node_id, port}` with `input_port`, **or** `edge_id` with `input_port`/`output_port` to insert. |
| `update_node` | `node_id`, optional `name` and `config`; both save together. |
| `connect` | `source_node_id`, `target_node_id`, `source_port`, `target_port`. Identical connections are deduplicated. |
| `remove` | `node_ids` and `edge_ids` arrays. Incident edges are removed with nodes. |
| `move_nodes` | `positions: [{node_id, position_x, position_y}]`; one gesture can move many nodes. |
| `pin` | `node_id`, `source: "run"`, `node_run_id`; or `source: "manual"` with optional item-array `input` and port-keyed `output`. |
| `unpin` | `node_id`. |
| `undo` / `redo` | `edit_id` from an original edit receipt. |

Insertion preserves the selected edge ID while redirecting its target to the new
step. A second edge continues from the selected output to the original target
and target port. Other branches and saved positions stay untouched.

Incomplete configurations are allowed in drafts. Unknown fields and invalid
provided values are rejected. Publish and test still require a valid graph.
Nodes expose `input_ports` and `output_ports`, including configured switch cases
and wait commands, so the UI can use the actual saved ports.

## Retry, conflict and history

Use a fresh UUID for a new request. If its response is lost, resend the **exact**
request, including the original UUID and expected hash. The stored receipt is
returned without applying the edit again. The graph in that response is current;
it may already include later changes. If its hash differs from the receipt's
`after_hash`, refresh the client history instead of treating it as that old state.

A stale hash, a UUID reused with a different body, or undo/redo against an
incompatible state returns HTTP **409**. Missing or foreign graph IDs return
**404**; invalid operation fields, settings or ports return **422**. Resolve the
conflict by reading the graph again and reviewing the intended change. The editor
blocks further writes after an uncertain save until retry or reload, and retains
pending settings for existing steps for review before an explicit save.

The browser's undo stack lasts for the editor session. Receipts persist in
`graph_edits` to support retries and restore exact IDs, positions and pins.
They contain full before/after editor documents, including configuration and
pinned samples, and are separate from execution history. Hosts should account for
this table in their normal data retention and access policies. Removing receipts
ends their retry and undo availability; this package does not prune them
implicitly. Workflow hard deletion cascades to its receipts.

## PHP and MCP

```php
use Aitumalow\Services\WorkflowGraphService;
use Illuminate\Support\Str;

$graphs = app(WorkflowGraphService::class);
$graph = $graphs->get($workflow);
$edited = $graphs->edit($workflow, [
    'request_id' => (string) Str::uuid(),
    'expected_hash' => $graph['hash'],
    'operation' => 'update_node',
    'data' => ['node_id' => $nodeId, 'name' => 'Assign owner', 'config' => []],
]);
```

Optional MCP tools `get_workflow_graph` and `edit_workflow_graph` use the same
service and request contract. The older complete-document draft API remains
available for replacement workflows; its aliases and replacement semantics are
not the editor history protocol. Both remain behind host-owned access controls.

## Inspecting test data

`POST /workflows/{workflow}/test-node` accepts an optional `expected_graph_hash`.
When supplied, Laravel checks it under the graph lock before capturing the test
revision. `payload` is an array of objects. The response identifies a run and its
`workflow_revision_id`; the editor polls that run for results and never merges
node results from separate runs. A later graph edit marks the displayed test as
stale. Historical-run input/output and pinned samples are explicitly selected.
Testing captures an immutable revision without changing the active revision.
