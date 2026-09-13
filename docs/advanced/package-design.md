# Designing a Laravel workflow builder

Aitumalow's direction is a workflow builder that feels at home inside a Laravel
application: visual composition, understandable data flow, and inspectable runs.
The application supplies its business actions. Durable Workflow supplies reliable
execution. n8n is a reference for the authoring experience.

This study inspected n8n at commit
[`4169b55bf3b3e6c255d7361642bc5243bd04345a`](https://github.com/n8n-io/n8n/tree/4169b55bf3b3e6c255d7361642bc5243bd04345a).
The implementation in Aitumalow is original; no n8n source, styles, assets, or
dependencies were incorporated.

## What to learn from n8n

The useful unit of inspiration is an interaction and the contract supporting it.
A rounded node card is easy to imitate. Remembering which output a user is
continuing, placing the next step sensibly, and recovering from a failed save
are what make that card useful.

| Pattern observed in n8n | Aitumalow's interpretation | Source to study |
| --- | --- | --- |
| An empty workflow offers a trigger first | Guide the first choice using the existing catalog's `type`. Laravel still validates the graph. | [CanvasNodeAddNodes.vue](https://github.com/n8n-io/n8n/blob/4169b55bf3b3e6c255d7361642bc5243bd04345a/packages/frontend/editor-ui/src/features/workflows/canvas/components/elements/nodes/render-types/CanvasNodeAddNodes.vue) |
| The node creator remembers its opening context | One picker accepts an optional source port and canvas position. Adding after a branch differs from adding an unconnected step. | [nodeCreator.store.ts](https://github.com/n8n-io/n8n/blob/4169b55bf3b3e6c255d7361642bc5243bd04345a/packages/frontend/editor-ui/src/features/shared/nodeCreator/nodeCreator.store.ts) |
| A connection ending without a target is an authoring gesture | Dropping an output on empty canvas opens the compatible-action picker at that location. | [Canvas.vue](https://github.com/n8n-io/n8n/blob/4169b55bf3b3e6c255d7361642bc5243bd04345a/packages/frontend/editor-ui/src/features/workflows/canvas/components/Canvas.vue) |
| Keyboard commands and viewport controls support large graphs | Add, find, and fit are local canvas commands. Search locates existing steps; it is separate from catalog search. | [Canvas.vue keyboard mapping](https://github.com/n8n-io/n8n/blob/4169b55bf3b3e6c255d7361642bc5243bd04345a/packages/frontend/editor-ui/src/features/workflows/canvas/components/Canvas.vue#L500) |
| Node descriptions declare ports and configuration properties | The Laravel registry already supplies labels, ports, configuration schemas, and output schemas to the UI. Extend this contract when a real capability requires it. | [INodeTypeDescription](https://github.com/n8n-io/n8n/blob/4169b55bf3b3e6c255d7361642bc5243bd04345a/packages/workflow/src/interfaces.ts#L3060) |
| Node inspection has input, configuration, and output spaces | The expanded step workspace shows input, settings, and output together, with an explicit data source. | [useNdvLayout.ts](https://github.com/n8n-io/n8n/blob/4169b55bf3b3e6c255d7361642bc5243bd04345a/packages/frontend/editor-ui/src/features/ndv/panel/composables/useNdvLayout.ts) |

These are design interpretations, not claims that the two products have identical
semantics. For example, Aitumalow currently validates exactly one trigger and
keeps its existing top-to-bottom graph orientation.

## Keep three clear owners

| Owner | Responsibilities | Existing Aitumalow seam |
| --- | --- | --- |
| Host Laravel application | Domain actions, models, authorization, tenancy, credentials, and event/webhook ingress | `WorkflowAction`, `WorkflowSubject`, `ExecutionScopeResolver`, explicit registration |
| Aitumalow | Capability catalog, graph editing and validation, publication, execution snapshots, and run inspection | `NodeRegistry`, `WorkflowService`, editor SDK, HTTP and optional MCP adapters |
| Durable Workflow | Activity execution, retries, timers, waiting, scheduling, history, and recovery | Private `src/Runtime` adapters and `DurableBoundaryTest` |

An editor gesture should reach an Aitumalow application service through the
injected SDK. A business side effect should reach a registered host action through
Durable. This keeps the same workflow usable through Filament, a custom host UI,
PHP, and MCP without implementing business rules in each surface.

The package already has these boundaries. A new engine, connector marketplace,
credential manager, or application router would add ownership that this product
does not need.

## A small action contract is a strength

`WorkflowAction` has three methods: `schema()`, `outputSchema()`, and
`handle(WorkflowContext $context)`. The `#[WorkflowNode]` attribute supplies a
stable key and presentation metadata. `NodeRegistry` projects that information
into the editor catalog, and `WorkflowActionNode` adapts execution to graph ports.

For an action called **Assign owner**, the names have different jobs:

| Value | Job |
| --- | --- |
| `app.request.assign_owner` | Persisted capability identity |
| `Assign owner` | Human-readable label that can improve without renaming the capability |
| The host's PHP action class | Container-resolved implementation; absent from the saved graph |

Keep ordinary host actions easy to write. Add a richer node contract only for
behavior that needs it, such as multiple output ports. A connector-sized
interface would make every simple Laravel action pay for features it does not
use.

Published graph revisions freeze configuration. They do not freeze the deployed
PHP implementation. A future capability-versioning policy should distinguish
changes to labels from changes to inputs, outputs, or business behavior, and
respect Durable's separate replay compatibility rules.

## Canvas and step workspace

The first pass concentrates on adding and finding steps:

- **Add trigger** guides an empty workflow to a starting point; subsequent
  catalog browsing omits additional triggers.
- **+** after an output remembers that branch. Dropping the output onto empty
  canvas does the same at the drop location.
- **Add step** uses the visible viewport. Double-clicking empty canvas supplies
  a specific location. Placement avoids existing node and sticky-note bounds.
- Existing positions remain unchanged. **Auto Layout** stays an explicit action.
- **Find step** searches the current workflow, including steps outside the
  viewport, and centers the chosen step beside its settings on desktop.
- Keyboard access supplements the visible controls. The minimap pans and zooms;
  it occupies a different corner from the main zoom controls.
- **+** on a connection inserts a step between its endpoints. For multiple ports,
  choose where the existing path enters and continues before inserting.
- Adding and connecting is one atomic edit. A retry reuses the same request UUID.
- Undo and redo restore saved identities, positions, connections, and pins.
  Auto Layout and dragging a selection each make one edit.
- Double-click a step, or expand its sidebar, to see input, settings and output
  together. Smaller screens use tabs. Unsaved settings remain attached to their
  step as you navigate; saving the name and configuration is one edit.
- Data inspection explicitly selects a pinned sample, the latest draft test, or
  a historical run. Draft test data is marked stale after graph edits. Results
  from different runs are never merged. Tables preview items; JSON shows the
  complete value.

## Editing a graph as one operation

n8n exposes an insertion control on a connection in
[CanvasEdgeToolbar.vue](https://github.com/n8n-io/n8n/blob/4169b55bf3b3e6c255d7361642bc5243bd04345a/packages/frontend/editor-ui/src/features/workflows/canvas/components/elements/edges/CanvasEdgeToolbar.vue).
For Aitumalow, the important follow-up is the operation behind that control.

Inserting B into **A → C** should produce **A → B → C** atomically. It must
preserve the chosen ports, existing node identities, positions, and draft/live
separation. Retrying after a network failure must not duplicate B. A concurrent
edit must produce a comprehensible conflict.

`WorkflowDraftService::replace()` already demonstrates a transaction, a workflow
lock, and an expected draft hash. It is not an editor undo or insertion API:
it deletes and recreates nodes, and its draft projection does not carry canvas
positions or pinned data. Reusing it blindly would discard authoring state.

`WorkflowGraphService` implements this separate editor contract. Every graph
writer takes a workflow row lock. An expected graph hash protects against stale
writes, and a persisted `WorkflowGraphEdit` receipt makes an exact request retry
idempotent. The service changes only the mutable node/edge graph. HTTP, PHP and
MCP all call it; the React store owns selection and the current session's undo
and redo stacks.

Undo restores a server-recorded snapshot only when the current graph matches the
original edit's `after_hash`; redo requires its `before_hash`. Existing rows keep
their identities. A deleted row can be restored with its original ID, so historical
node-run references remain meaningful. Published revisions and live activation
are separate from these editor receipts. Direct host database writes bypass the
package lock protocol; host integrations should use the services.

Graph settings may be incomplete while being authored. Supplied values are checked
against the registered capability schema, and publication still runs full graph
and required-field validation. This distinction lets someone add a condition
before filling out its required field. Ports derived from a switch or wait's
configuration are projected by Laravel for both rendering and connection checks.

See [Graph editing API](./graph-editing.md) for request examples, conflict behavior,
upgrade steps and the receipt lifecycle.

## A practical way to improve package design

Study one interaction end to end. For **add an action after a branch**, trace:

1. Host registration produces one catalog entry with a stable key and ports.
2. The picker retains the selected source node and output port.
3. The SDK sends one graph edit containing the new node and its source connection.
4. Laravel validates the graph and publishes an immutable revision.
5. A run captures a snapshot; Durable invokes the registered action.
6. Aitumalow projects the result for inspection.

Then change one thing: add a third branch, fail the connection request, move a
step off-screen, or rename its label. Check which layer needs to change. If every
layer needs special handling for one application action, the contract is leaking
application details. If only the host registration changes and the editor adapts,
the package boundary is doing useful work.

After the canvas and graph-editing pass, the next UX priority is the node
workspace: incoming data, parameters, and actual output together, with a clear
distinction between draft configuration, pinned examples, and results from a
specific run. That is the next substantial step toward the requested n8n-like
experience.
