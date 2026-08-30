<?php

declare(strict_types=1);

use Aitumalow\Contracts\ScheduledSubjectSource;
use Aitumalow\Contracts\WorkflowSubject;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\ScheduledWorkflowOccurrence;
use Aitumalow\Enums\NodeType;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Registry\ScheduledSubjectSourceRegistry;
use Aitumalow\Registry\WorkflowSubjectRegistry;
use Aitumalow\Runtime\DiscoverScheduledSubjectsActivity;
use Aitumalow\Services\WorkflowService;
use Carbon\CarbonImmutable;
use Workflow\V2\Models\WorkflowSchedule;

final class TestScheduledRecordSubject implements WorkflowSubject
{
    public function key(): string
    {
        return 'test.scheduled_record';
    }

    public function resolve(string $reference): mixed
    {
        return $reference;
    }

    public function reference(mixed $subject): string
    {
        return (string) $subject;
    }

    public function canStart(ExecutionScope $scope, mixed $subject): bool
    {
        return true;
    }

    public function canCommand(ExecutionScope $scope, mixed $subject, string $command, array $payload): bool
    {
        return true;
    }

    public function context(mixed $subject): array
    {
        return ['reference' => (string) $subject];
    }

    public function freshness(mixed $subject): ?string
    {
        return null;
    }

    public function allowedCapabilities(mixed $subject): array
    {
        return [];
    }
}

final class TestDailySubjectSource implements ScheduledSubjectSource
{
    public function key(): string
    {
        return 'test.daily_subjects';
    }

    public function subjectType(): string
    {
        return 'test.scheduled_record';
    }

    public function validateConfiguration(array $configuration): array
    {
        return ['timezone' => $configuration['timezone'] ?? 'UTC'];
    }

    public function cron(array $configuration): string
    {
        return '0 8 * * *';
    }

    public function timezone(array $configuration): string
    {
        return (string) $configuration['timezone'];
    }

    public function occurrences(array $configuration, CarbonImmutable $scheduledAt): iterable
    {
        foreach (['record-a', 'record-b'] as $reference) {
            yield new ScheduledWorkflowOccurrence(
                subjectReference: $reference,
                idempotencyKey: $scheduledAt->toDateString().':'.$reference,
                payload: [['scheduled_for' => $scheduledAt->toDateString()]],
            );
        }
    }
}

it('owns scheduled discovery and starts one idempotent subject run per occurrence', function () {
    app(WorkflowSubjectRegistry::class)->register(new TestScheduledRecordSubject);
    app(ScheduledSubjectSourceRegistry::class)->register(new TestDailySubjectSource);

    $workflow = Workflow::factory()->create([
        'key' => 'test.scheduled-records',
        'settings' => ['subject_type' => 'test.scheduled_record'],
    ]);
    $trigger = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Trigger,
        'node_key' => 'core.host_schedule',
        'config' => [
            'source' => 'test.daily_subjects',
            'configuration' => ['timezone' => 'Africa/Johannesburg'],
        ],
    ]);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'config' => ['fields' => ['discovered' => true]],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    app(WorkflowService::class)->activate($workflow);
    $schedule = WorkflowSchedule::query()->where('schedule_id', 'aitumalow.workflow.'.$workflow->id)->sole();

    expect(data_get($schedule->getAttribute('action'), 'workflow_type'))->toBe('aitumalow.subject_schedule.v1')
        ->and(data_get($schedule->getAttribute('spec'), 'timezone'))->toBe('Africa/Johannesburg');

    $arguments = [
        $workflow->id,
        $workflow->fresh()->active_revision_id,
        'test.daily_subjects',
        ['timezone' => 'Africa/Johannesburg'],
        (new ExecutionScope)->toArray(),
        '2026-08-29T08:00:00+02:00',
    ];
    $activity = app(DiscoverScheduledSubjectsActivity::class);
    $first = $activity->handle(...$arguments);
    $second = $activity->handle(...$arguments);

    expect($first)->toBe($second)
        ->and($workflow->runs()->count())->toBe(2)
        ->and($workflow->runs()->pluck('subject_reference')->sort()->values()->all())
        ->toBe(['record-a', 'record-b']);
});

it('requires stable unique scheduled source keys', function (): void {
    $registry = app(ScheduledSubjectSourceRegistry::class);
    $invalid = new class implements ScheduledSubjectSource
    {
        public function key(): string
        {
            return 'daily-subjects';
        }

        public function subjectType(): string
        {
            return 'test.scheduled_record';
        }

        public function validateConfiguration(array $configuration): array
        {
            return $configuration;
        }

        public function cron(array $configuration): string
        {
            return '0 8 * * *';
        }

        public function timezone(array $configuration): string
        {
            return 'UTC';
        }

        public function occurrences(array $configuration, CarbonImmutable $scheduledAt): iterable
        {
            return [];
        }
    };

    expect(fn () => $registry->register($invalid))
        ->toThrow(InvalidArgumentException::class);

    $registry->register(new TestDailySubjectSource);

    expect(fn () => $registry->register(new TestDailySubjectSource))
        ->toThrow(InvalidArgumentException::class);
});

it('rolls back activation when the scheduled subject boundary is incomplete', function (): void {
    app(ScheduledSubjectSourceRegistry::class)->register(new TestDailySubjectSource);

    $workflow = Workflow::factory()->create([
        'settings' => ['subject_type' => 'test.scheduled_record'],
    ]);
    $trigger = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Trigger,
        'node_key' => 'core.host_schedule',
        'config' => [
            'source' => 'test.daily_subjects',
            'configuration' => ['timezone' => 'UTC'],
        ],
    ]);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'core.set_fields',
        'config' => ['fields' => ['discovered' => true]],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    expect(fn () => app(WorkflowService::class)->activate($workflow))
        ->toThrow(LogicException::class, 'Scheduled subject type [test.scheduled_record] is not registered.')
        ->and($workflow->fresh()->is_active)->toBeFalse()
        ->and($workflow->fresh()->active_revision_id)->toBeNull()
        ->and(WorkflowSchedule::query()->where('schedule_id', 'aitumalow.workflow.'.$workflow->id)->exists())->toBeFalse();
});
