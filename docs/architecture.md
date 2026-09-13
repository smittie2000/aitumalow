# Aitumalow Architecture

Date: 2026-09-09

Status: Durable Workflow 2 stable runtime adopted

## Product boundary

Aitumalow is the Laravel and Filament layer that lets a host application expose
safe capabilities in an n8n-style visual builder. It is not a second workflow
engine and it is not a general code-execution platform.

```text
Host application
  registers stable trigger/action keys and owns business policy
                    |
Aitumalow
  editor, graph, validation, activation, run projections
                    |
Durable Workflow v2
  queue tasks, local activities, retries, timers, Updates, schedules, recovery
```

The stored graph selects stable keys such as `app.ticket.create`. The Laravel
container maps those keys to host code. Graphs never store PHP class names,
scripts, SQL, arbitrary URLs, model classes, or credentials.

## Runtime boundary

Aitumalow registers one Durable workflow type, `aitumalow.graph.v1`, and one
generic capability activity type, `aitumalow.capability.v1`. It does not generate
a PHP workflow or activity class for every visual definition.

At start time Aitumalow captures a serializable graph snapshot. The dynamic
Durable workflow deterministically walks that snapshot and invokes the generic
activity for each node. The activity resolves the node's stable key through the
host-populated registry and invokes the Laravel capability.

Package-owned `core.*` nodes use versioned Durable local activities to avoid a
separate queue trip for short package transformations. Host capabilities remain
ordinary queued activities so host routing, isolation, and business effects do
not move into the workflow worker.

Durable Workflow owns:

- workflow and activity task dispatch;
- activity retry and backoff;
- durable timers for delay nodes;
- Updates and timeout handling for wait nodes;
- recurring schedule state;
- workflow history and crash recovery; and
- execution concurrency inside its runtime.

Aitumalow owns:

- editable visual definitions and edges;
- immutable, content-addressed published revisions and the active revision pointer;
- the safe capability catalog and schemas;
- graph and configuration validation;
- immutable execution snapshots passed to Durable;
- activation and schedule synchronization; and
- small run, waiting-command, and node-run projections for its API and editor.
- scheduled host-subject discovery and one idempotent run per occurrence.

The projection IDs (`durable_workflow_id`, `durable_run_id`, and
`durable_activity_id`) correlate Aitumalow records with the authoritative Durable
history. They do not duplicate the Durable task ledger.

## Host capability boundary

The host owns its Eloquent models, policies, tenancy, domain actions, external
services, and transactions. Aitumalow owns no generic Eloquent trigger, model
picker, shell command, HTTP request, mail, notification, queued-job, or code node.
Those broad imported nodes were removed.

A host action implements `WorkflowAction` and is registered with a namespaced
`#[WorkflowNode]` key. Its `WorkflowContext::activityId()` is stable across
Durable retries and can be used as an idempotency key. Because activities are
at-least-once, the host action must still make its own side effect idempotent and
enforce authorization and tenant rules.

Hosts may also register a `WorkflowSubject` adapter. Aitumalow then resolves the
opaque subject reference, asks the adapter to authorize starts and commands,
captures bounded context/freshness, and restricts execution to capabilities the
adapter allows. Host models and users never become package schema.

Trigger ingress is also host-owned. The host verifies its webhook or application
event, resolves the actor and tenant, then starts the selected workflow. Aitumalow
does not auto-discover Eloquent models or subscribe arbitrary stored class names.

## Editor and transport boundary

The visual editor is a router-free embedded component backed by an injected
TypeScript SDK. The host owns page routing, authentication, authorization,
navigation, rate limiting, and Filament chrome. Hosts explicitly mount
`EditorApiRoutes` inside their middleware and explicitly install the Filament
plugin.

The same catalog and application services back HTTP, Filament, and the optional
MCP adapter. MCP does not own a transport, assistant, model provider, credentials,
prompt, approval UI, or a second capability list.

## Data and worker boundary

Aitumalow tables use an `aitumalow_` prefix. Durable Workflow publishes and owns
its own migrations. A production host must run both migration sets and operate
the Durable Workflow queue workers and schedule tick process described in that
package's documentation.

Catalogs are populated from service providers and remain stateless in long-lived
workers. Runtime state belongs in Durable history or Aitumalow projections, never
in a registry singleton or captured request object.

Workflow configuration and payloads are persisted. They must contain bounded
business data and opaque safe references, not secrets. Hosts resolve credentials
inside their own services.

## Runtime guarantees and limits

1. Drafts are mutable, published revisions are immutable, and a run pins one exact revision.
2. Stored keys resolve only capabilities explicitly registered by the host.
3. Durable owns retry, wait, timer, schedule, and recovery semantics.
4. Starts and commands have package idempotency identities; host side effects use the Durable activity ID.
5. Aitumalow run records are projections; Durable history is authoritative.
6. Package control nodes can branch or reshape data but cannot mutate host data.

Aitumalow does not duplicate Durable history, task, timer, retry, or update
tables. Its revision catalog is product configuration history; Durable's
fingerprints, patches, and worker-build compatibility separately protect PHP
workflow-code replay.

## Research lineage

The [package design guide](./advanced/package-design.md) maps concrete n8n
authoring patterns to Aitumalow's current contracts and explains the canvas
improvements and proposed graph-editing follow-up.

- Durable Workflow supplies the adopted Laravel durability runtime.
- n8n supplies visual composition and inspection inspiration.
- `aftandilmmd/laravel-workflow-automation` at commit
  `3fda4767ebbaf47caa7e33db90c2e39c837c1d80` is the attributed implementation
  baseline for the original editor, registry, and execution code.

The package is distributed under the MIT License and preserves the original
author's attribution.
