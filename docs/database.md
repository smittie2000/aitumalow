# Database schema

Aitumalow tables are prefixed to avoid colliding with Durable Workflow's canonical runtime tables.

| Table | Ownership |
| --- | --- |
| `aitumalow_workflows` | Editable visual workflow metadata |
| `aitumalow_workflow_nodes` | Stable capability keys and bounded configuration |
| `aitumalow_workflow_edges` | Visual graph connections |
| `aitumalow_workflow_graph_edits` | Idempotent edit receipts and before/after draft snapshots for undo/redo |
| `aitumalow_workflow_revisions` | Immutable content-addressed published graph revisions |
| `aitumalow_workflow_runs` | Editor/API run projection linked by `durable_workflow_id` and `durable_run_id` |
| `aitumalow_workflow_commands` | Idempotent host command projection linked to a Durable Update |
| `aitumalow_workflow_node_runs` | Editor node projection linked by `durable_activity_id` |
| `aitumalow_workflow_folders` | Editor organization |
| `aitumalow_workflow_tags` / `aitumalow_workflow_tag_pivot` | Editor organization |

Tables such as `workflow_runs`, `workflow_tasks`, activity executions, timers, Updates, schedule history, and failures are created and owned by `durable-workflow/workflow`. Aitumalow projections provide its stable product API; Durable history remains authoritative for runtime execution.

All Aitumalow table names and model classes remain configurable for host integration. The default prefix is deliberate and should usually be kept.

Graph-edit receipts include configuration and pinned samples. Their retention
belongs to the host and is independent of Durable execution history; see
[Graph editing API](/advanced/graph-editing#retry-conflict-and-history).
