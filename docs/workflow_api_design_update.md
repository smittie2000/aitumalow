# Workflow API Design Update

Date: 2026-08-29

Status: Implemented capability boundary with Durable Workflow v2 as the runtime

## Decision summary

Aitumalow is an n8n-style, low-code automation package for Laravel applications.
It lets an administrator compose workflows from capabilities supplied by the host
application. It does not replace the application's models, domain actions,
authorization, tenancy, or business rules.

The package owns workflow composition, configuration, scheduling, data mapping,
execution orchestration, and inspection. The host application owns the business
meaning and implementation of every model, trigger, and action exposed to a
workflow.

The intended end state is a headless application-automation architecture:

```text
Low-code editor / API / tool client
                 |
          Aitumalow workflow
                 |
     registered host capabilities
                 |
 Laravel models, policies, and actions
```

This updates the emphasis of `architecture.md`. Safe execution records,
publication, retries, and waits remain useful infrastructure, but Aitumalow is
not being designed as a general durable-workflow or BPM state-machine product.

## Product statement

The package should make this request configurable without moving host business
logic into the package:

> Department head A wants this business operation performed on this schedule,
> against these application records, using these variables.

For example:

```text
Every weekday at 08:00
  -> find open deals through a registered host query
  -> derive the owner, priority, and due date from mapped values
  -> call the host application's CreateTask action
```

The schedule and value mapping are workflow configuration. Selecting eligible
deals and creating a valid task are application business logic.

An administrator may change the schedule or mappings without a code deployment.
If the meaning of a valid task changes, the host application's action changes in
one place and every caller retains the same business rules.

## Ownership boundary

| Concern | Aitumalow owns | Host application owns |
|---|---|---|
| Workflow setup | Graph, node configuration, activation, and presentation metadata | Which actors may configure or activate it |
| Models and records | Safe references to registered subject keys and record identifiers | Eloquent models, scopes, relationships, tenancy, and policies |
| Triggers | Subscription configuration and dispatch into a workflow | Trigger implementations and authoritative application events |
| Actions | Node invocation, mapped input validation, output capture, and failure reporting | Laravel action implementation, domain validation, authorization, and side effects |
| Variables | Typed workflow inputs and mappings between node outputs and later inputs | The schemas and values a capability is allowed to expose or accept |
| Scheduling | Cron/calendar configuration and due execution dispatch | Tenant timezone policy and any domain-specific scheduling rules |
| Execution | Dynamic graph snapshot, node projections, and Durable runtime integration | Transactions, authorization, and idempotent side effects inside each business action |
| API and tools | Stable workflow and capability operations | Authentication, actor/tenant resolution, approvals, and rate limits |
| Administration UI | Optional Filament workflow editor and run inspection | Panel registration, navigation, permissions, and application chrome |

## Low-code workflow vocabulary

The package will use a small n8n-style vocabulary:

- A **workflow** is a configured graph of nodes.
- A **trigger node** starts an execution from a schedule, application event,
  manual request, or another explicitly registered source.
- An **action node** invokes a host-registered business capability.
- A **control node** performs package-owned flow behavior such as a condition,
  branch, delay, or iteration. These nodes do not mutate application data.
- An **execution** is one attempt to process a workflow from its trigger input.
- A **mapping** supplies a node input from a literal value, workflow input,
  trigger output, or an earlier node output.

Unlike n8n, the initial design will not execute arbitrary JavaScript expressions.
Mappings are declarative and schema checked. Stored definitions must not contain
PHP class names, raw SQL, unrestricted URLs, scripts, provider credentials, or
arbitrary model/table names.

## The host capability catalog

The host registers the capabilities Aitumalow is allowed to present and invoke.
Registration maps stable public keys to concrete Laravel code. Exact PHP names
and fluent syntax are deliberately not finalized in this document.

### Subject definitions

A subject definition describes an application-owned type that workflows may
reference, for example `app.deal` or `app.ticket`. It may expose:

- a stable key, label, and description;
- an authorized record resolver;
- safe readable fields and relationships;
- safe filters, selectors, or named query scopes;
- input and output schemas; and
- publication-time and execution-time authorization hooks.

This is a bounded adapter, not generic Eloquent access. Registering `app.deal`
does not give a workflow permission to query every column or call arbitrary
model methods.

### Trigger definitions

A trigger definition describes a safe way to start a workflow, for example:

- `core.schedule`;
- `core.manual`;
- `app.deal.updated`; or
- `app.ticket.created`.

The package may provide generic adapters for Laravel scheduling, queues, and
Eloquent events, but the host decides which subjects and events are registered.
An Eloquent event adapter only observes Eloquent writes; database-wide change
capture requires a separate host-provided outbox, CDC, or database-trigger
adapter.

### Action definitions

An action definition describes one application capability, for example
`app.tasks.create`, `app.deals.assign-owner`, or `app.tickets.escalate`. It
declares:

- a stable key and editor metadata;
- a typed configuration/input schema;
- a typed result schema;
- authorization and tenancy requirements;
- validation used before activation and again at execution time; and
- a Laravel-owned executor, normally an existing application Action.

The workflow engine invokes the action; it does not reproduce its business
logic.

### Catalog projection

One read-only catalog projection supplies the API, Filament editor, and tool
adapter with the same safe metadata. No UI or MCP layer maintains a second list
of capabilities.

## Host business aggregates

Tasks are a representative side effect, not a package domain. A workflow creates
or changes a task only by invoking a host-registered task action. The host owns
the task model, validation, assignment policy, notifications, and audit rules.

The same boundary applies to every host business aggregate. The host owns its
relationships, policies, documents, responsibilities, and operational dates.
Aitumalow owns only the registered workflow definition, publication, waiting
state, commands, execution, retry, and history used to orchestrate those host
capabilities.

This keeps two different concepts organized:

```text
Host business aggregate
  = application-owned relationships and business policy

Aitumalow workflow
  = reusable definition, state graph, command, and durable orchestration API

Durable Workflow
  = private execution engine used only by Aitumalow
```

## Definition and activation model

A configured workflow needs a safe boundary between editing and live execution,
but that does not turn the product into a BPM engine.

- Administrators edit a draft definition.
- Validation confirms that every referenced capability still exists, its
  configuration matches its schema, mappings are type compatible, and the actor
  may activate the workflow.
- Activation publishes a fixed definition and synchronizes its trigger.
- An execution uses the definition that started it, even if an administrator is
  editing the next draft.

This publication boundary is configuration deployment. It prevents a live edit
from changing an already queued or waiting execution. The package should keep
the persistence needed for this guarantee without introducing process-instance
or domain-state concepts that belong to the application.

## Execution boundary

Execution durability is supporting infrastructure, not the package's product
identity. Persist only what is needed to provide predictable automation:

- the activated definition or an equivalent immutable snapshot;
- execution and per-node status;
- bounded output and error diagnostics;
- idempotency and retry correlation;
- a resumable timestamp or callback reference for waits; and
- enough history to explain what happened.

The package owns reusable state-graph and human/external-command mechanics, but
not host models, task lifecycles, approval policy, or compensating business
transactions. Application state changes still happen only through registered
host actions.

## Headless and tool-friendly API

The editor is one client of the workflow API, not the owner of the workflow
model. The same application services should support HTTP controllers, Filament,
CLI commands, and an optional MCP adapter.

The public capability groups are:

1. **Catalog** — list registered subjects, triggers, actions, control nodes, and
   their schemas.
2. **Definitions** — create, read, edit, validate, and inspect workflow drafts.
3. **Activation** — activate, deactivate, and inspect the active definition.
4. **Executions** — start an allowed manual execution and inspect execution and
   node results.
5. **Operations** — retry, cancel, or command only where the workflow state and
   host policy allow it.

Tool responses use stable identifiers, explicit schemas, machine-readable error
codes, and bounded result payloads. MCP remains an optional adapter over these
services. It does not introduce a separate workflow API, and Aitumalow does not
own an assistant, model provider, chat thread, or approval UI.

## Lessons retained from Twenty and n8n

From n8n, retain the approachable composition model: triggers start workflows,
nodes process mapped data, and executions can be inspected. Do not inherit
unrestricted code expressions or an open catalog of generic mutation nodes.

From Twenty, retain:

- separate editable and active definitions;
- exact-definition execution and useful run inspection;
- trigger synchronization during activation;
- one graph projection for editing and execution status; and
- a closed backend dispatcher from stored keys to executors.

Improve on the coupling that does not fit a reusable Laravel package: the action
catalog is dynamically registered by the host instead of hard-coded into the
host product, and the package never owns the host object model.

## Implemented runtime

The earlier six-slice sequence has been superseded by adopting Durable Workflow
v2 as a dependency. Aitumalow now keeps one dynamic workflow type and one generic
capability activity rather than maintaining its imported graph executor, jobs,
retry loop, timer rows, resume jobs, and scheduler.

At execution start, the visual graph becomes a serializable immutable snapshot.
Durable walks it, persists its history, retries activities, manages timers and
signals, and starts recurring schedules. Aitumalow keeps only editor-facing run
projections correlated by Durable IDs.

The capability catalog, SDK, editor, HTTP API, and optional MCP adapter all use
the same stable-key registry. Unsafe generic built-ins and stored PHP/model class
names have been removed rather than carried forward.

## Historical implementation slices

The refactor will proceed through six narrow slices. A later slice must not be
started merely because an earlier one is documented.

### 1. Host capability catalog

Define the safe registration and read-only catalog contracts for subjects,
triggers, and actions. Remove or quarantine auto-discovery and broad built-in
business capabilities only as their registry-backed replacement is proven.

No graph persistence, runner, or MCP redesign belongs in this slice. The existing
editor and SDK may consume the catalog projection so the seam is proven end to
end, but they do not maintain a second capability list.

Acceptance criteria:

- the package boots with zero host business capabilities;
- a test host can explicitly register a Deal subject, Deal-updated trigger, and
  Create Task action;
- catalog output contains stable keys and schemas, not executable class names;
- duplicate and invalid keys fail deterministically;
- an unregistered model or action cannot be resolved; and
- the core catalog works without Filament, AI, or MCP packages.

### 2. Canonical workflow definition API

Define one trigger-and-node graph, declarative mappings, validation errors, and
draft/activation operations. Replace imported mutable DTOs only through this
tested seam.

### 3. Minimal execution kernel

Execute the activated graph against registered capabilities with typed inputs,
node outputs, idempotency, queue boundaries, and focused diagnostics. Add branch,
delay, retry, or iteration semantics only when a representative workflow needs
them.

### 4. Trigger and schedule adapters

Connect registered schedules and application events to the same execution entry
point. Prove transaction timing, tenant resolution, deduplication, and the limits
of Eloquent-only observation.

### 5. Headless API and optional MCP adapter

Expose the established catalog, definition, activation, and execution services
through stable, authorized transports. Tools never bypass the application
services or invoke arbitrary registered code by class name.

### 6. Filament low-code editor

Build the visual editor and run inspection over the same catalog and workflow
API. The host explicitly installs the Filament plugin; the core remains usable
headlessly.

## Explicit non-goals

- Replacing the host application's domain models or Laravel Actions.
- Treating Task, Deal Workstream, Ticket, or another host domain as package data.
- Providing arbitrary Eloquent, SQL, shell, PHP, JavaScript, URL, or credential
  execution.
- Becoming a BPMN suite or general human task/approval system.
- Owning authentication, tenancy, assistant conversations, AI providers, or
  application policy.
- Designing all six slices in implementation detail before slice 1 is proven.

## Capability catalog checkpoint

The existing node registry is being reduced into the one capability catalog
rather than introducing a parallel registry. Host registration is explicit,
keys are namespaced and duplicate-safe, and the read-only `/catalog` projection
drives the SDK and embedded palette. The representative proof is a host-owned
`app.whatsapp.conversation_started` trigger connected to a host-owned
`app.ticket.create` action.

This is a clean-break package API. It has no legacy key aliases or write-time
normalization. Ordinary host actions implement the small `WorkflowAction`
contract (`schema()` plus `handle(WorkflowContext)`), while the
`#[WorkflowNode]` attribute supplies the stable key and editor presentation
metadata. The engine adapter remains internal. The same seam is proven with a
`billing.payment.capture` action whose category, icon, field defaults, and
placeholders are projected to the SDK.

Broad imported business and code-execution built-ins have been removed. Trigger
ingress, model access, policies, tenant resolution, and publication authorization
remain host responsibilities exposed only through explicit registered seams.

## Current MCP adapter checkpoint

The optional MCP server projects the same `NodeRegistry` catalog and
`WorkflowService` operations already used by package clients. It does not own a
second capability list, MCP transport, authentication, tenancy, credentials,
provider settings, or an agent prompt.

The initial imported MCP surface has been reduced to fifteen operations:

- inspect registered workflow node definitions;
- inspect and edit workflow graphs;
- validate and activate workflows; and
- start an explicitly selected active workflow.

All tool results are structured MCP content. Graph mutations accept exact
registered keys, apply declared defaults, reject undeclared configuration
fields, and never accept PHP class names or arbitrary model names. Folders,
tags, credentials, pinned editor data, run browsing, broad registry operations,
and the package-authored workflow-builder prompt are outside this boundary.

The representative composition proof is the same host-owned WhatsApp
conversation-to-ticket flow used by the SDK catalog. The host receives and
verifies the provider webhook, resolves actor and tenant context, persists the
conversation, and then starts the configured workflow. MCP can compose that
workflow, but it is neither the webhook nor the application event handler.

## Current secret ownership checkpoint

The imported credential vault has been removed as a clean break. Aitumalow has
no secret model, migration, registry, resolver middleware, CRUD route, SDK
surface, or editor field. Persisted node configuration is never a secret store.

Host actions can already resolve application-owned integration services from
Laravel's container without exposing their secrets to the workflow. A future
opaque integration-key schema or host callback contract remains deliberately
undecided until representative integrations prove which information the editor
and runtime actually need.
