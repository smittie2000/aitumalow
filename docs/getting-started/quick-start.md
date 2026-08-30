# Quick start

## Register host capabilities

The host turns authenticated business operations into stable workflow keys:

```php
use Aitumalow\Facades\WorkflowAutomation;

WorkflowAutomation::register(UserRegisteredTrigger::class); // app.user.registered
WorkflowAutomation::register(SendWelcomeAction::class);     // app.user.send_welcome
```

Each class uses `#[WorkflowNode]` and implements `TriggerInterface`, `WorkflowAction`, or `NodeInterface`. The graph stores the keys, never the classes.

## Compose and activate

```php
use Aitumalow\Facades\Workflow;

$workflow = Workflow::create(['name' => 'Welcome new users']);
$trigger = Workflow::addNode($workflow, 'app.user.registered');
$welcome = Workflow::addNode($workflow, 'app.user.send_welcome', [
    'template' => 'welcome-v2',
]);

Workflow::connect($trigger, $welcome);
Workflow::activate($workflow);
```

The host trigger starts the graph by calling `Workflow::run($workflow, $payload)`. Aitumalow captures the visual graph and starts `aitumalow.graph.v1`; Durable Workflow executes each capability as a retryable activity.

## Run workers

Configure an asynchronous Laravel queue and run the Durable Workflow v2 workers. For schedule triggers, also register:

```php
Schedule::command('workflow:v2:schedule-tick')->everyMinute();
```

Mount `EditorApiRoutes` only inside a host-authenticated route group, then embed `<x-aitumalow::editor>` in a host-owned Filament or Blade page.
