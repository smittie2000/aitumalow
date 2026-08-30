# REST Endpoints

These endpoints are the optional browser-editor transport. Aitumalow does not
register them automatically. The host selects the URL and middleware:

```php
use Aitumalow\Http\EditorApiRoutes;
use Illuminate\Support\Facades\Route;

Route::prefix('workflow-engine')
    ->middleware(['web', 'auth'])
    ->name('aitumalow.')
    ->group(fn () => EditorApiRoutes::register());
```

The paths below use `/workflow-engine` only as an example host prefix.

## Workflows

### List Workflows

```http
GET /workflow-engine/workflows
```

Returns a paginated list of all workflows.

### Get Workflow

```http
GET /workflow-engine/workflows/{id}
```

Returns the workflow with its nodes and edges.

### Create Workflow

```http
POST /workflow-engine/workflows
Content-Type: application/json

{
  "name": "My Workflow",
  "description": "Optional description"
}
```

### Update Workflow

```http
PUT /workflow-engine/workflows/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "description": "New description"
}
```

### Delete Workflow

```http
DELETE /workflow-engine/workflows/{id}
```

### Activate Workflow

```http
POST /workflow-engine/workflows/{id}/activate
```

### Deactivate Workflow

```http
POST /workflow-engine/workflows/{id}/deactivate
```

### Run Workflow

```http
POST /workflow-engine/workflows/{id}/run
Content-Type: application/json

{
  "payload": [
    {"name": "Alice", "email": "alice@example.com"}
  ]
}
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "workflow_id": 1,
    "status": "completed",
    "started_at": "2024-01-15T08:00:00.000000Z",
    "finished_at": "2024-01-15T08:00:01.234000Z"
  }
}
```

### Duplicate Workflow

```http
POST /workflow-engine/workflows/{id}/duplicate
```

Creates a deep copy of the workflow with all nodes and edges. The copy is inactive by default.

### Validate Workflow

```http
POST /workflow-engine/workflows/{id}/validate
```

**Response (valid):**

```json
{
  "valid": true,
  "errors": []
}
```

**Response (invalid):**

```json
{
  "valid": false,
  "errors": [
    "Workflow has no trigger node.",
    "Node 'Send Email' has no incoming edges."
  ]
}
```

## Nodes

### Create Node

```http
POST /workflow-engine/workflows/{workflowId}/nodes
Content-Type: application/json

{
  "node_key": "app.mail.send",
  "name": "Welcome Email",
  "config": {
    "to": "{{ item.email }}",
    "subject": "Welcome!",
    "body": "Thanks for joining."
  }
}
```

### Update Node

```http
PUT /workflow-engine/workflows/{workflowId}/nodes/{nodeId}
Content-Type: application/json

{
  "name": "Updated Name",
  "config": {
    "to": "{{ item.new_email }}",
    "subject": "Updated subject",
    "body": "Updated body."
  }
}
```

### Delete Node

```http
DELETE /workflow-engine/workflows/{workflowId}/nodes/{nodeId}
```

### Update Node Position

```http
PATCH /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/position
Content-Type: application/json

{
  "position_x": 250,
  "position_y": 100
}
```

### Get Available Variables

```http
GET /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/variables
```

Returns all variables available to a node's expressions — globals, upstream node outputs, and built-in functions.

**Response:**

```json
{
  "globals": [
    { "path": "item", "type": "object", "label": "Current Item" },
    { "path": "payload", "type": "object", "label": "Initial Payload" },
    { "path": "trigger", "type": "array", "label": "Trigger Output" }
  ],
  "nodes": [
    {
      "node_id": 5,
      "node_name": "Find Deals",
      "node_key": "app.order.fetch",
      "variables": [
        { "path": "nodes.Find Deals.main.0.id", "type": "integer", "label": "Deal ID" },
        { "path": "nodes.Find Deals.main.0.owner_id", "type": "integer", "label": "Owner ID" }
      ]
    }
  ],
  "functions": [
    { "name": "upper", "args": "value", "label": "Uppercase" },
    { "name": "lower", "args": "value", "label": "Lowercase" }
  ]
}
```

### List Host References

```http
GET /workflow-engine/workflows/{workflowId}/references/{source}
```

Returns the labels and opaque values allowed by the registered host reference provider in this workflow's execution scope. For example, `source` may be `app.mailboxes.readable`. The host's route middleware remains responsible for editor access; the response never contains resolved resources or credentials.

```json
{
  "data": [
    {
      "value": "01K...MAILBOX",
      "label": "Quotes inbox",
      "description": "Inbound quote requests"
    }
  ]
}
```

### Pin Node Test Data

```http
POST /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/pin
Content-Type: application/json
```

**From a previous run:**

```json
{
  "source": "run",
  "node_run_id": 42
}
```

**Manual data:**

```json
{
  "source": "manual",
  "input": [{"name": "Alice", "email": "alice@example.com"}],
  "output": {"main": [{"name": "Alice", "status": "processed"}]}
}
```

When a node has pinned output, it is **skipped entirely** during test runs (`executeUpTo` / node testing) — the pinned output is returned directly. When a node has pinned input, it **executes normally** but receives the pinned input instead of computed input.

::: tip
Pinned data only affects test mode. Normal workflow runs (triggers, manual `start()`) ignore pinned data completely.
:::

### Unpin Node Test Data

```http
DELETE /workflow-engine/workflows/{workflowId}/nodes/{nodeId}/pin
```

Removes pinned data from the node. The node resumes normal execution during test runs.

## Edges

### Create Edge

```http
POST /workflow-engine/workflows/{workflowId}/edges
Content-Type: application/json

{
  "source_node_id": 1,
  "source_port": "main",
  "target_node_id": 2,
  "target_port": "main"
}
```

### Delete Edge

```http
DELETE /workflow-engine/workflows/{workflowId}/edges/{edgeId}
```

## Runs

### List Runs

```http
GET /workflow-engine/workflows/{workflowId}/runs
```

Returns paginated runs for a workflow.

### Get Run Details

```http
GET /workflow-engine/runs/{runId}
```

Returns the run with all node runs.

### Cancel Run

```http
POST /workflow-engine/runs/{runId}/cancel
```

Cancels a running or waiting run.

### Resume Run

```http
POST /workflow-engine/runs/{runId}/resume
Content-Type: application/json

{
  "payload": [{
    "approved": true,
    "comment": "Looks good"
  }]
}
```

Used with `core.wait_resume` nodes. The host must authorize the caller for the run.

### Replay Run

```http
POST /workflow-engine/runs/{runId}/replay
```

Creates a new run with the same payload as the original.


## Capability Catalog

### List Available Capabilities

```http
GET /workflow-engine/catalog
```

Returns the safe metadata for every explicitly registered capability. The
projection is shared by the editor and SDK and never includes executable class
names.

**Response:**

```json
[
  {
    "key": "app.ticket.create",
    "namespace": "app",
    "type": "action",
    "name": "Create support ticket",
    "category": "Support",
    "icon": "ticket",
    "description": "Calls the host-owned ticket creation action.",
    "config_schema": [
      {"key": "priority", "type": "select", "label": "Priority", "options": ["normal", "urgent"]}
    ],
    "input_ports": ["main"],
    "output_ports": ["main"]
  }
]
```
