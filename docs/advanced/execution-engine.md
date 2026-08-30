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

The activity resolves the stable key from the host's `NodeRegistry`, resolves expressions, runs middleware, invokes the capability once, and writes an editor-facing node-run projection. Package-owned `core.*` capabilities run as Durable local activities; host capabilities remain ordinary queued activities. Durable Workflow records both forms in history and applies their retry policy.

## Runtime flow

```text
Visual graph -> immutable start snapshot -> Durable workflow task
             -> local core activity or queued host activity -> Durable history
             -> Aitumalow run projection -> editor/API
```

Workflow code is deterministic and performs no database or network side effects. Host business effects happen only inside Durable activities. Aitumalow does not generate workflow PHP classes dynamically.

## Native primitives

- Node retry settings become Durable local or queued activity options.
- `core.delay` becomes a Durable timer.
- `core.wait_resume` accepts declared commands through a Durable Update, with an optional Durable timeout.
- `core.schedule` becomes a Durable schedule with skip-overlap behavior.
- Cancelling an Aitumalow run sends a Durable cancellation command.

The local-activity change is protected by Durable's
`aitumalow.core-local-activities.v1` patch marker. Runs whose history predates
that marker replay the original queued-activity branch.

Workers and operational commands are supplied by Durable Workflow. Configure an asynchronous Laravel queue connection and run the Durable workers described by that dependency. The `sync` queue driver is not supported in production.

## Projections are not the engine

`aitumalow_workflow_runs` and `aitumalow_workflow_node_runs` exist for the editor and API. Their `durable_*_id` columns link to Durable's canonical history. They are safe to rebuild and are never used for retry, timer, Update, or scheduling correctness. JSON resources serialize these projections without synchronizing or writing runtime state.
