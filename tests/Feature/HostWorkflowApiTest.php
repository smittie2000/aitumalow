<?php

declare(strict_types=1);

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\TriggerInterface;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\Contracts\WorkflowSubject;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\DTOs\SubjectReference;
use Aitumalow\DTOs\WorkflowContext;
use Aitumalow\DTOs\WorkflowStart;
use Aitumalow\Enums\NodeType;
use Aitumalow\Facades\Workflow as WorkflowFacade;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Models\Workflow;
use Aitumalow\Nodes\BaseNode;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

it('wraps subject authorization context and idempotent Durable start behind the Aitumalow API', function (): void {
    WorkflowAutomation::register(HostApiManualTrigger::class);
    WorkflowAutomation::register(RecordSubjectAction::class);
    WorkflowAutomation::subject(new CalendarEventSubjectAdapter);

    $workflow = Workflow::factory()->create([
        'settings' => ['subject_type' => 'app.calendar_event'],
    ]);
    $trigger = WorkflowFacade::addNode($workflow, 'app.workflow.manual');
    $action = WorkflowFacade::addNode($workflow, 'app.calendar_event.record');
    WorkflowFacade::connect($trigger, $action);
    WorkflowFacade::activate($workflow);

    $command = new WorkflowStart(
        payload: [['confirmation' => 'requested']],
        subject: new SubjectReference('app.calendar_event', 'event:42'),
        scope: new ExecutionScope('tenant:acme', 'user:7'),
        idempotencyKey: 'booking-confirmation:event:42:2026-08-29',
        executorReference: 'agent:booking-confirmation',
    );

    $first = WorkflowFacade::start($workflow->fresh(), $command);
    $duplicate = WorkflowFacade::start($workflow->fresh(), $command);

    expect($duplicate->is($first))->toBeTrue()
        ->and($first->durable_workflow_id)->toBe('aitumalow.run.'.$first->id)
        ->and($first->workflow_revision_id)->toBe($workflow->fresh()->active_revision_id)
        ->and($first->subject_type)->toBe('app.calendar_event')
        ->and($first->subject_reference)->toBe('event:42')
        ->and($first->subject_context)->toBe([
            'title' => 'Booking 42',
            'status' => 'booked',
        ])
        ->and($first->executor_reference)->toBe('agent:booking-confirmation')
        ->and($workflow->runs()->count())->toBe(1);

    $completed = $this->drainDurableRun($first);
    expect($completed->nodeRuns->firstWhere('node_id', $action->id)?->output)->toBe([
        'main' => [[
            'subject_type' => 'app.calendar_event',
            'subject_reference' => 'event:42',
            'subject_status' => 'booked',
            'subject_freshness' => 'event-version:3',
            'executor_reference' => 'agent:booking-confirmation',
        ]],
    ]);
});

it('keeps subject authorization in the host adapter while Aitumalow owns orchestration', function (): void {
    WorkflowAutomation::register(HostApiManualTrigger::class);
    WorkflowAutomation::register(RecordSubjectAction::class);
    WorkflowAutomation::subject(new CalendarEventSubjectAdapter);

    $workflow = Workflow::factory()->create([
        'settings' => ['subject_type' => 'app.calendar_event'],
    ]);
    $trigger = WorkflowFacade::addNode($workflow, 'app.workflow.manual');
    $action = WorkflowFacade::addNode($workflow, 'app.calendar_event.record');
    WorkflowFacade::connect($trigger, $action);
    WorkflowFacade::activate($workflow);

    WorkflowFacade::start($workflow->fresh(), new WorkflowStart(
        subject: new SubjectReference('app.calendar_event', 'event:42'),
        scope: new ExecutionScope('tenant:acme', 'user:denied'),
    ));
})->throws(AuthorizationException::class);

it('requires the host subject contract for subject-bound workflow starts', function (): void {
    WorkflowAutomation::register(HostApiManualTrigger::class);
    WorkflowAutomation::register(RecordSubjectAction::class);
    WorkflowAutomation::subject(new CalendarEventSubjectAdapter);

    $workflow = Workflow::factory()->create([
        'settings' => ['subject_type' => 'app.calendar_event'],
    ]);
    $trigger = WorkflowFacade::addNode($workflow, 'app.workflow.manual');
    $action = WorkflowFacade::addNode($workflow, 'app.calendar_event.record');
    WorkflowFacade::connect($trigger, $action);
    WorkflowFacade::activate($workflow);

    WorkflowFacade::run($workflow->fresh(), [['event_reference' => 'event:42']]);
})->throws(LogicException::class, 'requires subject [app.calendar_event]');

#[WorkflowNode(
    key: 'app.workflow.manual',
    name: 'Manual host start',
    category: 'Application',
    icon: 'play',
    type: NodeType::Trigger,
)]
final class HostApiManualTrigger extends BaseNode implements TriggerInterface
{
    public function inputPorts(): array
    {
        return [];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }

    public function register(int $workflowId, int $nodeId, array $config): void {}

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return is_array($event) ? [$event] : [[]];
    }
}

#[WorkflowNode(
    key: 'app.calendar_event.record',
    name: 'Record Calendar event context',
    category: 'Application',
    icon: 'calendar',
)]
final class RecordSubjectAction implements WorkflowAction
{
    public function schema(): array
    {
        return [];
    }

    public function outputSchema(): array
    {
        return [];
    }

    public function handle(WorkflowContext $context): array
    {
        return [
            'subject_type' => $context->subjectType(),
            'subject_reference' => $context->subjectReference(),
            'subject_status' => $context->subjectContext()['status'] ?? null,
            'subject_freshness' => $context->subjectFreshness(),
            'executor_reference' => $context->executorReference(),
        ];
    }
}

final class CalendarEventSubjectAdapter implements WorkflowSubject
{
    public function key(): string
    {
        return 'app.calendar_event';
    }

    public function resolve(string $reference): mixed
    {
        return ['reference' => $reference, 'version' => 3];
    }

    public function reference(mixed $subject): string
    {
        return $subject['reference'];
    }

    public function canStart(ExecutionScope $scope, mixed $subject): bool
    {
        return $scope->principalReference === 'user:7';
    }

    public function canCommand(ExecutionScope $scope, mixed $subject, string $command, array $payload): bool
    {
        return $scope->principalReference === 'user:7';
    }

    public function context(mixed $subject): array
    {
        return ['title' => 'Booking 42', 'status' => 'booked'];
    }

    public function freshness(mixed $subject): string
    {
        return 'event-version:'.$subject['version'];
    }

    public function allowedCapabilities(mixed $subject): array
    {
        return ['app.calendar_event.record'];
    }
}
