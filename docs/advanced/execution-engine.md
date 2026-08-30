# Durable execution

Aitumalow is a product layer on top of `durable-workflow/workflow` v2. Aitumalow owns the visual graph, stable capability catalog, validation, scoped references, and editor projections. Durable Workflow owns execution history, tasks, leases, retries, timers, signals, schedules, cancellation, and recovery.

Every run captures the current visual graph as a serializable snapshot. Stored nodes contain stable keys such as `app.ticket.create`; PHP class names are never stored in the graph.

The snapshot starts one stable workflow type:

```text
aitumalow.graph.v1
```

That deterministic workflow walks the snapshot and invokes one stable activity type for each node:

```text
aitumalow.capability.v1
```

The activity resolves the stable key from the host's `NodeRegistry`, resolves expressions, runs middleware, invokes the host capability once, and writes an editor-facing node-run projection. Durable Workflow records the result and applies the activity retry policy.

## Runtime flow

```text
Visual graph -> immutable start snapshot -> Durable workflow task
             -> capability activity task -> Durable history
             -> Aitumalow run projection -> editor/API
```

Workflow code is deterministic and performs no database or network side effects. Host business effects happen only inside Durable activities. Aitumalow does not generate workflow PHP classes dynamically.

## Native primitives

- Node retry settings become Durable `ActivityOptions`.
- `core.delay` becomes a Durable timer.
- `core.wait_resume` becomes the declared Durable `resume` signal, with an optional Durable timeout.
- `core.schedule` becomes a Durable schedule with skip-overlap behavior.
- Cancelling an Aitumalow run sends a Durable cancellation command.

Workers and operational commands are supplied by Durable Workflow. Configure an asynchronous Laravel queue connection and run the Durable workers described by that dependency. The `sync` queue driver is not supported in production.

## Projections are not the engine

`aitumalow_workflow_runs` and `aitumalow_workflow_node_runs` exist for the editor and API. Their `durable_*_id` columns link to Durable's canonical history. They are safe to rebuild and are never used for retry, timer, signal, or scheduling correctness.
