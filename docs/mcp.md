<div v-pre>

# MCP Server

Aitumalow ships an optional MCP server for composing and running workflows from
the same host-registered `#[WorkflowNode]` catalog used by the editor SDK. It
does not own an agent, model, conversation, credentials, or MCP transport.

## Setup

Install Laravel MCP and register the transport in the host application:

```bash
composer require laravel/mcp
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

## Tools

| Tool | Purpose | Annotation |
|------|---------|------------|
| `list_workflow_nodes` | List safe workflow node summaries from the host catalog | Read-only |
| `show_workflow_node` | Show one exact key with schemas and ports | Read-only |
| `list_workflow_references` | List scoped opaque values for a reference schema source | Read-only |
| `list_workflows` | List workflow drafts and active workflows | Read-only |
| `show_workflow` | Show one workflow graph | Read-only |
| `create_workflow` | Create an empty workflow draft | Mutating |
| `update_workflow` | Update its name or description | Idempotent |
| `add_workflow_node` | Add a registered node by exact stable key | Mutating |
| `update_workflow_node` | Replace a stored node's validated configuration | Idempotent |
| `remove_workflow_node` | Remove a stored node and connected edges | Destructive |
| `connect_workflow_nodes` | Connect two nodes in the same workflow | Mutating |
| `disconnect_workflow_nodes` | Remove a stored workflow edge | Destructive |
| `validate_workflow` | Validate graph structure and ports | Read-only |
| `activate_workflow` | Activate a valid workflow | Idempotent |
| `deactivate_workflow` | Deactivate a workflow | Idempotent |
| `run_workflow` | Start a workflow with host-shaped workflow items | Mutating |

Folders, tags, credentials, pinned editor data, arbitrary models, and generic
registry operations are intentionally not part of this MCP boundary.

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
middleware for the MCP transport. Tool arguments are workflow data, not an
authorization boundary. Provider, model, conversation, and credential IDs must
not be accepted as substitutes for the authenticated host actor.

</div>
