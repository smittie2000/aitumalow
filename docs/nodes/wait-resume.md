# Wait / command

`core.wait_resume` pauses the Durable workflow until Aitumalow submits an
accepted command through a Durable Update or the optional timeout elapses.

| Config | Type | Meaning |
|---|---|---|
| `state_key` | string | Optional stable product state projected while this node waits |
| `timeout_seconds` | integer | Zero waits indefinitely; a positive value enables `timeout` |
| `commands` | array | Stable command keys and labels; defaults to `resume` |

Each configured command key becomes an output port. This lets human approvals,
external callbacks, and Deal transitions use the same graph primitive without a
host-owned step/attempt engine.

```php
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\RunCommand;
use Aitumalow\Facades\Workflow;

$command = Workflow::command($run, new RunCommand(
    name: 'approve',
    idempotencyKey: 'deal-workstream:42:approval:1',
    payload: ['approved_by' => 'user:42'],
    scope: new ExecutionScope('tenant:acme', 'user:42'),
    expectedState: 'awaiting_approval',
));
```

Aitumalow resolves its private waiting node and validates the optional expected
stable state plus declared command before
submitting the Durable Update. Reusing an idempotency key returns the same
package command; the package does not implement a competing signal/update
engine.

Cycles are allowed only when every cycle crosses a durable wait node or a
positive delay. This supports long-lived state machines such as cancel/reopen
without permitting hot in-memory loops. While waiting, Aitumalow exposes the
configured state as `WorkflowRun::waiting_state`; hosts do not infer it from
editor node IDs or inspect Durable state.

For subject-bound runs, the caller scope is required and the host's registered
`WorkflowSubject` adapter authorizes the command. Public callbacks remain
host-owned: the host validates its signed opaque callback, then maps it to this
Aitumalow API without exposing Durable identifiers as credentials.
