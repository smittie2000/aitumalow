<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\Models\Workflow;
use Aitumalow\Registry\ScheduledSubjectSourceRegistry;
use Aitumalow\Registry\WorkflowSubjectRegistry;
use Workflow\V2\Enums\ScheduleOverlapPolicy;
use Workflow\V2\Enums\ScheduleStatus;
use Workflow\V2\Support\ScheduleManager;

final readonly class ScheduleSynchronizer
{
    public function __construct(
        private WorkflowSnapshot $snapshots,
        private ExecutionScopeResolver $scopes,
        private ScheduledSubjectSourceRegistry $sources,
        private WorkflowSubjectRegistry $subjects,
    ) {}

    public function activate(Workflow $workflow): void
    {
        $trigger = $workflow->triggerNode();
        $schedule = ScheduleManager::findByScheduleId($this->scheduleId($workflow));

        if (! in_array($trigger?->node_key, ['core.schedule', 'core.host_schedule'], true)) {
            if ($schedule !== null && $schedule->getAttribute('status') === ScheduleStatus::Active) {
                ScheduleManager::pause($schedule, 'Aitumalow workflow no longer uses a schedule trigger.');
            }

            return;
        }

        $config = $trigger->config ?? [];
        $revision = $workflow->activeRevision;
        if ($revision === null) {
            throw new \LogicException("Workflow {$workflow->id} has no active revision to schedule.");
        }
        if ($trigger->node_key === 'core.host_schedule') {
            $sourceKey = is_string($config['source'] ?? null) ? $config['source'] : '';
            $source = $this->sources->get($sourceKey);
            $subjectType = $source->subjectType();
            if (! $this->subjects->has($subjectType)) {
                throw new \LogicException("Scheduled subject type [{$subjectType}] is not registered.");
            }
            $configuredSubjectType = data_get($revision->definition, 'settings.subject_type');
            if ($configuredSubjectType !== $subjectType) {
                throw new \LogicException(
                    "Scheduled source [{$sourceKey}] provides subject [{$subjectType}], not [{$configuredSubjectType}].",
                );
            }
            $configuration = $source->validateConfiguration(
                is_array($config['configuration'] ?? null) ? $config['configuration'] : [],
            );
            $workflowClass = ScheduledSubjectDispatchWorkflow::class;
            $cron = $source->cron($configuration);
            $timezone = $source->timezone($configuration);
            $arguments = [
                $workflow->id,
                $revision->id,
                $sourceKey,
                $configuration,
                $this->scopes->resolve($workflow)->toArray(),
            ];
        } else {
            $workflowClass = DynamicGraphWorkflow::class;
            $cron = (string) $config['cron'];
            $timezone = (string) ($config['timezone'] ?? 'UTC');
            $snapshot = $this->snapshots->fromRevision($revision);
            $arguments = [
                $snapshot,
                [['schedule_id' => $this->scheduleId($workflow)]],
                $this->scopes->resolve($workflow)->toArray(),
                0,
                null,
            ];
        }

        if ($schedule === null) {
            ScheduleManager::create(
                scheduleId: $this->scheduleId($workflow),
                workflowClass: $workflowClass,
                cronExpression: $cron,
                arguments: $arguments,
                timezone: $timezone,
                overlapPolicy: ScheduleOverlapPolicy::Skip,
                labels: [
                    'aitumalow_workflow_id' => (string) $workflow->id,
                    'aitumalow_revision_id' => (string) $revision->id,
                ],
                queue: (string) config('aitumalow.queue', 'default'),
                notes: "Managed by Aitumalow workflow {$workflow->id}.",
            );

            return;
        }

        ScheduleManager::update(
            schedule: $schedule,
            cronExpression: $cron,
            timezone: $timezone,
            overlapPolicy: ScheduleOverlapPolicy::Skip,
            action: [
                'workflow_type' => $trigger->node_key === 'core.host_schedule'
                    ? 'aitumalow.subject_schedule.v1'
                    : 'aitumalow.graph.v1',
                'workflow_class' => $workflowClass,
                'input' => $arguments,
            ],
        );

        if ($schedule->fresh()->getAttribute('status') === ScheduleStatus::Paused) {
            ScheduleManager::resume($schedule->fresh());
        }
    }

    public function deactivate(Workflow $workflow): void
    {
        $schedule = ScheduleManager::findByScheduleId($this->scheduleId($workflow));

        if ($schedule !== null && $schedule->getAttribute('status') === ScheduleStatus::Active) {
            ScheduleManager::pause($schedule, 'Aitumalow workflow deactivated.');
        }
    }

    public function delete(Workflow $workflow): void
    {
        $schedule = ScheduleManager::findByScheduleId($this->scheduleId($workflow));

        if ($schedule !== null) {
            ScheduleManager::delete($schedule);
        }
    }

    private function scheduleId(Workflow $workflow): string
    {
        return 'aitumalow.workflow.'.$workflow->id;
    }
}
