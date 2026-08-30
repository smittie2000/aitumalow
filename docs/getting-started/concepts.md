<div v-pre>

# Core Concepts

## Two layers, one workflow

Aitumalow stores and validates the visual graph. Durable Workflow executes its
immutable start snapshot and owns queue tasks, activity retries, timers, signals,
schedules, history, and recovery.

```text
visual graph -> Aitumalow snapshot -> Durable workflow -> registered host action
```

An Aitumalow run and node run are inspection projections correlated with Durable
workflow, run, and activity IDs. Durable history is the execution authority.

## Workflow graph

A workflow is a directed graph with exactly one trigger. Edges route a named
output port to a named input port. Data moves as a list of associative-array
items.

```text
[Trigger] -> [Condition] -- true  --> [Host action]
                         -- false --> [Transform]
```

The package provides only composition primitives:

| Kind | Built-ins |
|---|---|
| Triggers | Manual, Schedule |
| Conditions | If, Switch |
| Controls | Delay, Loop, Merge, Wait/Resume |
| Transformers | Parse Data, Set Fields |
| Utilities | Aggregate, Filter |
| Annotation | Sticky Note |

Business triggers and actions are registered by the host under stable keys such
as `app.ticket.created` and `app.ticket.assign`. Aitumalow deliberately provides
no generic model, HTTP, mail, notification, shell, queued-job, or code-execution
node.

## Immutable start snapshot

Starting a run captures node keys, validated configuration, ports, edges, names,
and execution settings. Editing the database graph later cannot alter that
running or waiting Durable execution. Aitumalow does not need a second task
ledger to provide this guarantee.

## Host capabilities

The registry is the only route from a persisted key to Laravel code. The host
owns model access, policies, tenancy, credentials, business validation,
transactions, and side effects. Its action receives `WorkflowContext`, including
a Durable activity ID that remains stable across retries and can be used as an
idempotency key.

Activities are at-least-once. A host action that sends, charges, creates, or
updates must make that operation idempotent.

## Expressions

Configuration fields that opt into expressions can read bounded runtime data:

```text
{{ item.email }}
{{ payload.0.tenant_id }}
{{ nodes.Find open deals.main.0.id }}
```

Available roots are `item`, `payload`, `trigger`, `node`, and `nodes`. Stored
expressions are declarative; arbitrary PHP or JavaScript is not executed.

## Durable states

The editor projects these run states: pending, running, waiting, completed,
failed, and cancelled. Delay nodes use Durable timers. Wait/Resume nodes use a
Durable signal with an optional timeout. Activity retry and backoff are handled
by Durable, not by the node runner.

## Activation

Activation validates the graph and synchronizes `core.schedule` with Durable's
schedule manager. Deactivation pauses that schedule. Manual and host-owned
ingress call the same `WorkflowService::run()` boundary with a bounded payload
and resolved execution scope.

## Next

- [Quick Start](/getting-started/quick-start)
- [Custom Nodes](/advanced/custom-nodes)
- [Execution Engine](/advanced/execution-engine)
- [Schedule Trigger](/triggers/schedule)

</div>
