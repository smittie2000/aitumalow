# Events

Durable Workflow v2 dispatches the canonical workflow/activity lifecycle events. Listen to its `Workflow\V2\Events` classes when runtime identity and committed history timing matter.

Aitumalow dispatches editor-projection events after its linked rows change:

| Event | Meaning |
| --- | --- |
| `Aitumalow\Events\NodeExecuted` | A capability activity updated its node projection successfully |
| `Aitumalow\Events\NodeFailed` | A capability attempt updated its node projection with an error |
| `Aitumalow\Events\WorkflowCompleted` | A Durable completion was projected into an Aitumalow run |
| `Aitumalow\Events\WorkflowFailed` | A Durable terminal failure was projected into an Aitumalow run |

These events are integration conveniences, not an execution protocol. Do not implement retries, timers, scheduling, or recovery in listeners; Durable Workflow already owns those guarantees.
