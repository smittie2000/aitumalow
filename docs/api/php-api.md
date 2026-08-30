# PHP API

## Fluent API (Workflow Model)

The fluent API lets you build and manage workflows directly on model instances.

### Creating a Workflow

```php
use Aitumalow\Models\Workflow;

$workflow = Workflow::create([
    'name'        => 'My Workflow',
    'description' => 'Optional description',
    'folder_id'   => $folder->id,  // Optional: assign to folder
]);
```

With tags (via service or facade):

```php
$workflow = Workflow::create([
    'name'    => 'My Workflow',
    'tag_ids' => [$tag1->id, $tag2->id],
]);
```

### Adding Nodes

```php
$node = $workflow->addNode(string $name, string $nodeKey, array $config = []): WorkflowNode
```

Returns the created `WorkflowNode` instance.

```php
$trigger = $workflow->addNode('Start', 'core.manual');

$email = $workflow->addNode('Send Email', 'app.mail.send', [
    'to'      => '{{ item.email }}',
    'subject' => 'Hello {{ item.name }}',
    'body'    => 'Welcome!',
]);
```

### Connecting Nodes

```php
$target = $source->connect(
    int|WorkflowNode $target,
    string $sourcePort = 'main',
    string $targetPort = 'main',
): WorkflowNode  // returns the target node for chaining
```

Examples:

```php
// Simple connection
$trigger->connect($email);

// Named ports
$condition->connect($vipEmail, sourcePort: 'true');
$condition->connect($standardEmail, sourcePort: 'false');

// Chaining (connect returns target)
$trigger->connect($check)->connect($email, sourcePort: 'true');
```

### Activating / Deactivating

```php
$workflow->activate(): static    // Publishes the draft and selects that immutable revision
$workflow->deactivate(): static  // Sets is_active = false, returns $this
```

Publishing the same unchanged draft is idempotent. Editing nodes after
activation changes only the next draft; active and historical runs continue to
use their pinned revisions.

### Running

```php
// Synchronous execution
$run = $workflow->start(array $payload = []): WorkflowRun

// Async (queued) execution
$workflow->start(array $payload = []): WorkflowRun
```

The payload is an array of items:

```php
$run = $workflow->start([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);
```

### Validation

```php
$errors = $workflow->validateGraph(): array // Returns string[] of errors
```

### Tags & Folders

```php
// Assign tags (replaces existing)
$workflow->attachTags([$tag1->id, $tag2->id]): static

// Remove specific tags
$workflow->detachTags([$tag1->id]): static

// Remove all tags
$workflow->detachTags(): static

// Move to a folder
$workflow->moveToFolder($folder): static       // WorkflowFolder instance
$workflow->moveToFolder($folder->id): static   // or integer ID

// Remove from folder
$workflow->moveToFolder(null): static
```

All methods return `$this` for chaining:

```php
$workflow->attachTags([1, 2])->moveToFolder($folder)->activate();
```

### Other Operations

```php
$copy = $workflow->duplicate(): Workflow  // Deep copy with nodes, edges, and tags
$workflow->removeNode(int $nodeId): void
$workflow->removeEdge(int $edgeId): void
```

## Facade API

The `Workflow` facade delegates to `WorkflowService`. All methods accept either model instances or integer IDs.

```php
use Aitumalow\Facades\Workflow;
```

### Host product wrappers

Hosts should install reusable workflows through an Aitumalow definition DTO,
not by creating graph rows or referring to the underlying runtime.

Use `StateWorkflowDefinition` for product lifecycles. The host supplies states,
transitions, metadata, and one registered business capability; Aitumalow builds
the wait/command/routing graph, publishes an immutable revision, and activates
it idempotently.

```php
use Aitumalow\DTOs\StateWorkflowDefinition;
use Aitumalow\DTOs\WorkflowState;
use Aitumalow\DTOs\WorkflowTransition;

$revision = Workflow::installStateWorkflow(new StateWorkflowDefinition(
    key: 'order_delivery',
    name: 'Order delivery',
    initialState: 'active',
    transitionCapability: 'shop.delivery.transition',
    states: [
        new WorkflowState('active', 'Active'),
        new WorkflowState('completed', 'Completed'),
    ],
    transitions: [
        new WorkflowTransition('complete', 'Complete', 'active', 'completed'),
    ],
    subjectType: 'shop.order',
));
```

Use `WorkflowBlueprint` for non-state-machine workflows. Node aliases are
stable definition-local strings; hosts never store or connect database node
IDs.

```php
use Aitumalow\DTOs\WorkflowBlueprint;

$revision = Workflow::installWorkflow(new WorkflowBlueprint(
    key: 'booking_confirmation',
    name: 'Booking confirmation',
    subjectType: 'app.booking',
    nodes: [
        ['id' => 'schedule', 'capability' => 'core.host_schedule', 'config' => [
            'source' => 'app.daily_bookings',
            'configuration' => ['time' => '08:00', 'timezone' => 'UTC'],
        ]],
        ['id' => 'prepare', 'capability' => 'app.booking.prepare'],
    ],
    edges: [['from' => 'schedule', 'to' => 'prepare']],
));
```

Both installers are content-addressed. Reinstalling an unchanged definition
returns the active revision; changing the definition creates and activates a
new immutable revision while existing runs remain pinned.

### Execution

```php
Workflow::run(int|Workflow $workflow, array $payload = []): WorkflowRun
Workflow::start(int|Workflow $workflow, WorkflowStart $command): WorkflowRun
Workflow::command(int|WorkflowRun $run, RunCommand $command): WorkflowCommand
Workflow::commandAndWait(int|WorkflowRun $run, RunCommand $command): WorkflowCommand
Workflow::cancel(int|WorkflowRun $run): WorkflowRun
Workflow::replay(int|WorkflowRun $run): WorkflowRun
```

`run()` is the simple unbound convenience API. Product integrations should use
`start()` so Aitumalow can own subject authorization, caller idempotency, and
Durable instance correlation:

```php
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\SubjectReference;
use Aitumalow\DTOs\WorkflowStart;

$run = Workflow::start($workflow, new WorkflowStart(
    payload: [['confirmation' => 'requested']],
    subject: new SubjectReference('app.calendar_event', 'event:42'),
    scope: new ExecutionScope('tenant:acme', 'user:7'),
    idempotencyKey: 'booking-confirmation:event:42:2026-08-29',
    executorReference: 'agent:booking-confirmation',
));
```

`command()` returns after the command is durably accepted. Request/response
hosts that must return the applied business state can use `commandAndWait()`.
Both are Aitumalow contracts; hosts never inspect the underlying engine's
update objects or lifecycle.

### Host tests

Host test suites can execute Aitumalow inline without selecting a queue backend
or importing runtime jobs and task models:

```php
use Aitumalow\Testing\WorkflowTestHarness;

protected function setUp(): void
{
    parent::setUp();

    app(WorkflowTestHarness::class)->fake();
}
```

Ready starts and commands execute inline. Use `drain($run)` when a test needs to
advance delayed work. The harness is guarded to the `testing` environment.

### CRUD

```php
Workflow::create(array $data): Workflow           // $data may include 'tag_ids' => [...]
Workflow::update(int|Workflow $workflow, array $data): Workflow  // same
Workflow::delete(int|Workflow $workflow): void
Workflow::duplicate(int|Workflow $workflow): Workflow            // copies tags
```

### State

```php
Workflow::activate(int|Workflow $workflow): Workflow
Workflow::publish(int|Workflow $workflow, ?string $principalReference = null): WorkflowRevision
Workflow::restoreDraft(int|Workflow $workflow, int|WorkflowRevision $revision): Workflow
Workflow::deactivate(int|Workflow $workflow): Workflow
Workflow::validate(int|Workflow $workflow): array
```

`restoreDraft()` atomically copies a published version back into the mutable
editor graph, including its settings, pinned test data, and saved layout. It
does not move the active revision pointer; call `activate()` separately after
reviewing the restored draft.

### Builder

```php
Workflow::addNode(int|Workflow $workflow, string $nodeKey, array $config = [], ?string $name = null): WorkflowNode
Workflow::connect(int|WorkflowNode $source, int|WorkflowNode $target, string $sourcePort = 'main', string $targetPort = 'main'): WorkflowEdge
Workflow::removeNode(int $nodeId): void
Workflow::removeEdge(int $edgeId): void
```

## Models

### Workflow

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `name` | string | Workflow name |
| `description` | string\|null | Optional description |
| `is_active` | bool | Whether the workflow can be triggered |
| `active_revision_id` | int\|null | Exact published revision used by new runs |
| `settings` | array\|null | Global settings (e.g. retry_count) |

**Relationships:** `nodes()`, `edges()`, `revisions()`, `activeRevision()`, `runs()`, `tags()`, `folder()`

**Helper:** `triggerNode()` — returns the single trigger node

### WorkflowTag

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `name` | string | Tag name (unique) |
| `color` | string\|null | Hex color code (e.g. `#FF0000`) |

**Relationships:** `workflows()`

### WorkflowFolder

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `name` | string | Folder name |
| `parent_id` | int\|null | Parent folder for nesting |

**Relationships:** `parent()`, `children()`, `workflows()`

**Helper:** `ancestors()` — returns array of parent folders from root to direct parent

### WorkflowNode

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `workflow_id` | int | Parent workflow |
| `type` | NodeType | trigger, action, condition, transformer, control, utility, code |
| `node_key` | string | Stable capability key (e.g. `app.mail.send`) |
| `name` | string\|null | Display name |
| `config` | array | Node configuration |
| `position_x` | int | UI X position |
| `position_y` | int | UI Y position |

### WorkflowEdge

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `workflow_id` | int | Parent workflow |
| `source_node_id` | int | Source node |
| `source_port` | string | Source port name (default: `main`) |
| `target_node_id` | int | Target node |
| `target_port` | string | Target port name (default: `main`) |

### WorkflowRun

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `workflow_id` | int | Workflow being executed |
| `workflow_revision_id` | int | Immutable published revision being executed |
| `status` | RunStatus | pending, running, waiting, completed, failed, cancelled |
| `trigger_node_id` | int\|null | Which trigger started the run |
| `waiting_node_id` | int\|null | Expected node for the next package command |
| `waiting_state` | string\|null | Stable product state declared by the current wait node |
| `subject_type` / `subject_reference` | string\|null | Registered adapter key and opaque host reference |
| `initial_payload` | array\|null | Original payload |
| `context` | array\|null | Node output snapshots |
| `error_message` | string\|null | Error details (if failed) |
| `started_at` | timestamp\|null | When execution began |
| `finished_at` | timestamp\|null | When execution ended |

### WorkflowNodeRun

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Primary key |
| `workflow_run_id` | int | Parent run |
| `node_id` | int | Which node was executed |
| `status` | NodeRunStatus | pending, running, completed, failed, skipped |
| `input` | array\|null | Items received |
| `output` | array\|null | Items produced (by port) |
| `error_message` | string\|null | Error details |
| `duration_ms` | int\|null | Execution time in milliseconds |
| `attempts` | int | Number of attempts |
| `executed_at` | timestamp\|null | When executed |

## Enums

### RunStatus

```php
use Aitumalow\Enums\RunStatus;

RunStatus::Pending    // 'pending'
RunStatus::Running    // 'running'
RunStatus::Waiting    // 'waiting'
RunStatus::Completed  // 'completed'
RunStatus::Failed     // 'failed'
RunStatus::Cancelled  // 'cancelled'
```

### NodeRunStatus

```php
use Aitumalow\Enums\NodeRunStatus;

NodeRunStatus::Pending   // 'pending'
NodeRunStatus::Running   // 'running'
NodeRunStatus::Completed // 'completed'
NodeRunStatus::Failed    // 'failed'
NodeRunStatus::Skipped   // 'skipped'
```

### NodeType

```php
use Aitumalow\Enums\NodeType;

NodeType::Trigger
NodeType::Action
NodeType::Condition
NodeType::Transformer
NodeType::Control
NodeType::Utility
NodeType::Code
```

### Operator

```php
use Aitumalow\Enums\Operator;

Operator::Equals         // 'equals'
Operator::NotEquals      // 'not_equals'
Operator::Contains       // 'contains'
Operator::NotContains    // 'not_contains'
Operator::GreaterThan    // 'greater_than'
Operator::LessThan       // 'less_than'
Operator::GreaterOrEqual // 'greater_or_equal'
Operator::LessOrEqual    // 'less_or_equal'
Operator::IsEmpty        // 'is_empty'
Operator::IsNotEmpty     // 'is_not_empty'
Operator::StartsWith     // 'starts_with'
Operator::EndsWith       // 'ends_with'
```

### AggregateFunction

```php
use Aitumalow\Enums\AggregateFunction;

AggregateFunction::Sum   // 'sum'
AggregateFunction::Count // 'count'
AggregateFunction::Avg   // 'avg'
AggregateFunction::Min   // 'min'
AggregateFunction::Max   // 'max'
```
