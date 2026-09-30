<div v-pre>

# MCP Server

Aitumalow ships an optional MCP server for composing and running workflows from
the same host-registered `#[WorkflowNode]` catalog used by the editor SDK. An agent
uses this interface as a user: it discovers available steps, configures and saves
workflow data, reviews results and uses the same controls as the editor. Building
a workflow does not require generating PHP in the host application. The package
does not own an agent, model, conversation, credentials, or MCP transport.

## Setup

Install Laravel MCP and register the transport in the host application:

```bash
composer require 'laravel/mcp:^1.0.1'
```

```php
use Aitumalow\Mcp\WorkflowMcpServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/aitumalow', WorkflowMcpServer::class)
    ->middleware(['auth:sanctum', 'can:manage-workflows', 'throttle:60,1']);
```

For a private local agent process, the host may instead register:

```php
Mcp::local('aitumalow', WorkflowMcpServer::class);
```

The transport registration is a one-time installation concern, following
[Laravel MCP server registration](https://laravel.com/docs/13.x/mcp#server-registration).
Connect an agent to that endpoint with the same authenticated principal, tenant
resolution and workflow permissions used by the editor. Each new workflow is
stored data; it needs no new route, PHP class, action or application deployment.
An agent can use everything the editor's registered catalog exposes. The host's
existing integrations still determine which business actions are available.

## Contract

The MCP server distinguishes between:

- A **workflow node definition**, registered by the host under an exact stable
  key such as `app.ticket.create`.
- A **stored workflow node**, which is one configured instance of that
  definition inside a workflow graph.

MCP clients cannot provide PHP class names or invent configuration fields.
`add_workflow_node` and `update_workflow_node` validate configuration against
the registered definition before persistence. Defaults declared in `schema()`
are applied by the package.

Tool results use MCP structured content so clients receive predictable objects
instead of parsing prose or JSON embedded in text.

For whole-graph edits, `get_workflow_draft` returns a structured graph with
document-local node aliases and a `draft_hash`. The client sends the complete
edited graph and that hash to `save_workflow_draft`. The save is transactional:
unknown capabilities, invalid configuration, invalid ports, disconnected
graphs, and stale hashes leave the previous draft untouched. It never publishes
or moves the active revision pointer.

For incremental editor gestures, `get_workflow_graph` returns persistent row IDs,
positions, pinned samples and a separate concurrency hash. Use
`edit_workflow_graph` with that hash and a request UUID for atomic insertion,
configuration, moves, pins or undo/redo. Exact retries reuse the UUID and body.
See the [graph editing contract](/advanced/graph-editing) for operation fields and
conflict handling.

This is deliberately a JSON graph contract, not executable PHP or TypeScript.
The package does not add a second workflow DSL or an agent-controlled code
execution surface.

## Tools

| Tool | Purpose | Annotation |
|------|---------|------------|
| `list_workflow_nodes` | Search the editor catalog; `include_schemas:true` includes ports and configuration in one call | Read-only |
| `show_workflow_node` | Show one exact key with schemas and ports | Read-only |
| `list_workflow_references` | List scoped opaque values for a reference schema source | Read-only |
| `list_workflows` | Search/filter/sort workflows by folder, tag and active status | Read-only |
| `show_workflow` | Show one workflow graph | Read-only |
| `get_workflow_draft` | Get an editable whole-graph document and concurrency hash | Read-only |
| `get_workflow_graph` | Get the editor graph with stable row IDs and its concurrency hash | Read-only |
| `create_workflow` | Create an empty draft or a complete validated graph atomically, with settings/folder/tags | Mutating |
| `save_workflow_draft` | Atomically validate and replace the complete mutable graph | Mutating |
| `edit_workflow_graph` | Apply one atomic edit, retry its receipt, or undo/redo an edit | Mutating |
| `update_workflow` | Update name, description, settings, folder and tags | Idempotent |
| `add_workflow_node` | Add a registered node by exact stable key | Mutating |
| `update_workflow_node` | Replace a stored node's validated configuration | Idempotent |
| `remove_workflow_node` | Remove a stored node and connected edges | Destructive |
| `connect_workflow_nodes` | Connect two nodes in the same workflow | Mutating |
| `disconnect_workflow_nodes` | Remove a stored workflow edge | Destructive |
| `validate_workflow` | Validate graph structure and ports | Read-only |
| `activate_workflow` | Activate a valid workflow | Idempotent |
| `deactivate_workflow` | Deactivate a workflow | Idempotent |
| `run_workflow` | Start a workflow with host-shaped workflow items | Mutating |
| `show_workflow_run` | Inspect status/commands; `include_data:true` includes editor input/output samples | Read-only |
| `duplicate_workflow` | Copy the draft into an inactive workflow with no inherited publication | Mutating |
| `delete_workflow` | Delete a workflow and pause its schedule | Destructive |
| `get_workflow_variables` | Discover the same expression paths/functions as the editor | Read-only |
| `test_workflow_node` | Test the current draft through a selected node with pins and optional graph hash | Mutating |
| `list_workflow_runs` | Browse execution history, including draft tests, with status filtering/pagination | Read-only |
| `control_workflow_run` | Cancel, resume or replay using the editor runtime controls | Destructive |
| `list_workflow_revisions` | List immutable versions and identify the live version | Read-only |
| `compare_workflow_revision` | Review a published version alongside the current draft | Read-only |
| `restore_workflow_draft` | Restore a version into the draft without changing the live version | Destructive |
| `list_workflow_organization` | List folders (flat/tree) and tags with workflow counts | Read-only |
| `manage_workflow_folder` | Create/update/delete folders with the editor's cycle and nonempty safeguards | Destructive |
| `manage_workflow_tag` | Create/update/delete tags with shared editor validation | Destructive |

Credentials, arbitrary models and registry registration remain installation
concerns rather than workflow authoring controls. Canvas zoom and selection are
client state; node positions, pins, insertion, removal and undo/redo use
`edit_workflow_graph`. The two organization mutation tools combine create,
update and delete and carry the conservative destructive annotation.

MCP clients should preserve unrelated nodes and edges when saving a complete
draft. They must fetch again after a stale-hash error. Draft saves do not imply
permission to activate or run: those tools are separate operations and should
only be called when the user explicitly requests them.

`run_workflow` is asynchronous: it returns the Aitumalow run ID and initial
projection status, not a completed workflow result. Use `show_workflow_run` to
inspect progress. Workflows configured for a registered host subject must be
started through the host's subject-aware PHP API rather than this generic tool.

`test_workflow_node` runs the draft through the chosen node and can execute
upstream business actions, exactly like **Test step**. It records an immutable
execution snapshot without changing the live version or activation. Its
`expected_graph_hash` comes from `get_workflow_graph`, not `get_workflow_draft`.
Inspect the returned run with `show_workflow_run(include_data:true)` to see the
same samples as the editor. Replay repeats the original revision and payload.

## Agent authoring without app PHP

For an existing catalog, the shortest authoring path is two tool calls:

```text
list_workflow_nodes(search: "lead", include_schemas: true)
create_workflow(
  name: "Email owner when lead status changes",
  nodes: [
    { id: "event", capability: "<discovered lead-status key>" },
    { id: "email", capability: "<discovered owner-email key>",
      config: { "<discovered message field>": "Please review your lead." } }
  ],
  edges: [{ from: "event", to: "email" }]
)
```

The placeholders must come from the catalog response, including every required
configuration field. Creation validates the entire graph in a transaction. On
failure, no empty workflow or partial graph is left behind. Creation returns an
inactive draft and a hash; activating it is a separate requested operation.
The saved draft opens normally in the editor, and later UI edits are visible
through MCP.

The same contract handles open-ticket reminders or daily 08:00 booking
confirmation: discover the schedule, query and action steps, set their declared
configuration, then create their connected graph. No domain-specific MCP tool
or PHP workflow class is needed.

The [browser acceptance host](/advanced/browser-testing) includes a real HTTP
MCP test: discover the lead catalog, create a draft, edit it through visible UI
controls, observe that edit through MCP, test it through the real queue and
inspect the captured owner email and run samples. The proof exercises the
protocol and editor together; it does not evaluate a particular LLM's planning.

## Real example: WhatsApp conversation to support ticket

Assume the host registered these definitions:

```text
app.whatsapp.conversation_started
app.ticket.create
```

An MCP client follows the catalog rather than guessing their configuration:

```text
list_workflow_nodes(category: "Support")
show_workflow_node(key: "app.whatsapp.conversation_started")
show_workflow_node(key: "app.ticket.create")

create_workflow(
  name: "Open a ticket for each WhatsApp conversation",
  description: "The host owns webhook verification and starts this workflow."
)

add_workflow_node(
  workflow_id: 41,
  key: "app.whatsapp.conversation_started"
)

add_workflow_node(
  workflow_id: 41,
  key: "app.ticket.create",
  config: { "priority": "normal" }
)

connect_workflow_nodes(
  source_workflow_node_id: 100,
  target_workflow_node_id: 101
)

validate_workflow(workflow_id: 41)
activate_workflow(workflow_id: 41)
```

The WhatsApp provider still calls a host-owned webhook. After authentication,
tenant resolution, and persistence, the host starts workflow `41` with its own
payload:

```json
[
  {
    "tenant_id": 7,
    "conversation_id": 42,
    "contact_id": 99
  }
]
```

The MCP server never receives provider secrets and does not become the webhook.

## Real example: capture an approved order payment

For host definitions `commerce.order.approved` and
`billing.payment.capture`, the agent first inspects the payment schema. The
registered definition may expose:

```json
{
  "gateway": "stripe",
  "amount": "{{ item.order.total }}"
}
```

Those are configuration fields declared by the host action. MCP validates them,
stores the stable key, and the Laravel container resolves the host's
`ProcessPaymentAction` only when the workflow executes.

## Security

The host owns authentication, authorization, tenancy, rate limits, and audit
middleware for the MCP transport. Apply the same actor and tenant scope to
both interfaces; shared services alone do not grant or enforce a host's
transport permissions. A local stdio transport must also establish that scope.
Tool arguments are workflow data, not an
authorization boundary. Provider, model, conversation, and credential IDs must
not be accepted as substitutes for the authenticated host actor.

</div>
