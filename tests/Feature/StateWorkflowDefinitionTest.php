<?php

declare(strict_types=1);

use Aitumalow\Attributes\WorkflowNode;
use Aitumalow\Contracts\WorkflowAction;
use Aitumalow\Contracts\WorkflowSubject;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\RunCommand;
use Aitumalow\DTOs\StateWorkflowDefinition;
use Aitumalow\DTOs\SubjectReference;
use Aitumalow\DTOs\WorkflowContext;
use Aitumalow\DTOs\WorkflowStart;
use Aitumalow\DTOs\WorkflowState;
use Aitumalow\DTOs\WorkflowTransition;
use Aitumalow\Facades\Workflow;
use Aitumalow\Facades\WorkflowAutomation;
use Aitumalow\Models\Workflow as WorkflowModel;
use Aitumalow\Testing\WorkflowTestHarness;

it('installs an idempotent state workflow without exposing graph or Durable mechanics to the host', function (): void {
    WorkflowAutomation::register(StateTransitionCapability::class);

    $definition = new StateWorkflowDefinition(
        key: 'crm_delivery',
        name: 'Application delivery',
        initialState: 'active',
        transitionCapability: 'app.delivery.transition',
        states: [
            new WorkflowState('active', 'Active', ['lifecycle' => 'active']),
            new WorkflowState('completed', 'Completed', ['lifecycle' => 'completed']),
            new WorkflowState('cancelled', 'Cancelled', ['lifecycle' => 'cancelled']),
        ],
        transitions: [
            new WorkflowTransition('complete', 'Complete', 'active', 'completed', ['ability' => 'delivery.complete']),
            new WorkflowTransition('cancel', 'Cancel', 'active', 'cancelled', []),
            new WorkflowTransition('reopen', 'Reopen', 'cancelled', '@previous', []),
        ],
        subjectType: 'app.delivery',
    );

    $first = Workflow::installStateWorkflow($definition, 'user:1');
    $same = Workflow::installStateWorkflow($definition, 'user:1');
    $workflow = WorkflowModel::query()->where('key', 'crm_delivery')->sole();
    $graph = $first->stateGraph();

    expect($same->is($first))->toBeTrue()
        ->and($first->ulid)->not->toBeEmpty()
        ->and($workflow->active_revision_id)->toBe($first->id)
        ->and($workflow->revisions()->count())->toBe(1)
        ->and($graph->initialState())->toBe('active')
        ->and($graph->state('completed')->metadata)->toBe(['lifecycle' => 'completed'])
        ->and($graph->transitionsFrom('active')[0]->metadata)->toBe(['ability' => 'delivery.complete'])
        ->and($workflow->nodes()->where('node_key', 'core.wait_resume')->count())->toBe(3)
        ->and($workflow->nodes()->where('node_key', 'app.delivery.transition')->count())->toBe(3);
});

it('routes the command caller scope into the host business capability', function (): void {
    app(WorkflowTestHarness::class)->fake();
    WorkflowAutomation::register(StateTransitionCapability::class);
    WorkflowAutomation::subject(new DeliverySubjectAdapter);

    Workflow::installStateWorkflow(new StateWorkflowDefinition(
        key: 'crm_delivery_scope',
        name: 'Application delivery scope',
        initialState: 'active',
        transitionCapability: 'app.delivery.transition',
        states: [
            new WorkflowState('active', 'Active', []),
            new WorkflowState('completed', 'Completed', []),
        ],
        transitions: [new WorkflowTransition('complete', 'Complete', 'active', 'completed', [])],
        subjectType: 'app.delivery',
    ));

    $workflow = Workflow::findByKey('crm_delivery_scope');
    $run = Workflow::start($workflow, new WorkflowStart(
        subject: new SubjectReference('app.delivery', 'delivery:42'),
        scope: new ExecutionScope(principalReference: 'user:starter'),
    ));
    $command = Workflow::commandAndWait($run, new RunCommand(
        name: 'complete',
        idempotencyKey: 'delivery:42:complete',
        scope: new ExecutionScope(principalReference: 'user:commander'),
        expectedState: 'active',
    ));
    $action = $workflow->nodes()->where('node_key', 'app.delivery.transition')->firstOrFail();

    expect($command->result)->toBe(['accepted' => true, 'command' => 'complete', 'node_id' => $run->waiting_node_id])
        ->and($run->nodeRuns()->where('node_id', $action->id)->latest('id')->firstOrFail()->output)
        ->toBe([
            'main' => [[
                'target_state' => 'completed',
                'principal' => 'user:commander',
            ]],
        ]);
});

#[WorkflowNode(key: 'app.delivery.transition', name: 'Transition delivery', category: 'Application')]
final class StateTransitionCapability implements WorkflowAction
{
    public function schema(): array
    {
        return [['key' => 'transition', 'type' => 'json', 'label' => 'Transition', 'required' => true]];
    }

    public function outputSchema(): array
    {
        return [
            ['key' => 'target_state', 'type' => 'string', 'label' => 'Target state'],
            ['key' => 'principal', 'type' => 'string', 'label' => 'Command principal'],
        ];
    }

    public function handle(WorkflowContext $context): array
    {
        return [
            'target_state' => $context->get('transition.target_state'),
            'principal' => $context->scope()->principalReference,
        ];
    }
}

final class DeliverySubjectAdapter implements WorkflowSubject
{
    public function key(): string
    {
        return 'app.delivery';
    }

    public function resolve(string $reference): mixed
    {
        return $reference;
    }

    public function reference(mixed $subject): string
    {
        return is_string($subject) ? $subject : '';
    }

    public function canStart(ExecutionScope $scope, mixed $subject): bool
    {
        return $scope->principalReference === 'user:starter';
    }

    public function canCommand(ExecutionScope $scope, mixed $subject, string $command, array $payload): bool
    {
        return $scope->principalReference === 'user:commander';
    }

    public function context(mixed $subject): array
    {
        return [];
    }

    public function freshness(mixed $subject): ?string
    {
        return null;
    }

    public function allowedCapabilities(mixed $subject): array
    {
        return ['app.delivery.transition'];
    }
}
