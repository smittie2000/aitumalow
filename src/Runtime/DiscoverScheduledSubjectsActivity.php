<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\SubjectReference;
use Aitumalow\DTOs\WorkflowStart;
use Aitumalow\Models\Workflow;
use Aitumalow\Registry\ScheduledSubjectSourceRegistry;
use Aitumalow\Services\WorkflowService;
use Carbon\CarbonImmutable;
use Workflow\V2\Activity;
use Workflow\V2\Attributes\Type;

#[Type('aitumalow.discover_scheduled_subjects.v1')]
final class DiscoverScheduledSubjectsActivity extends Activity
{
    public function __construct(
        private readonly ScheduledSubjectSourceRegistry $sources,
        private readonly WorkflowService $workflows,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, string|null>  $scope
     * @return list<int>
     */
    public function handle(
        int $workflowId,
        int $revisionId,
        string $sourceKey,
        array $configuration,
        array $scope,
        string $scheduledAt,
    ): array {
        $workflow = Workflow::query()->findOrFail($workflowId);
        if (! $workflow->is_active || $workflow->active_revision_id !== $revisionId) {
            return [];
        }

        $source = $this->sources->get($sourceKey);
        $configuration = $source->validateConfiguration($configuration);
        $runIds = [];

        foreach ($source->occurrences($configuration, CarbonImmutable::parse($scheduledAt)) as $occurrence) {
            $run = $this->workflows->start($workflow, new WorkflowStart(
                payload: $occurrence->payload,
                subject: new SubjectReference($source->subjectType(), $occurrence->subjectReference),
                scope: $occurrence->scope ?? ExecutionScope::fromArray($scope),
                idempotencyKey: $occurrence->idempotencyKey,
                idempotencyScope: "schedule:{$workflow->key}:{$sourceKey}",
                executorReference: $occurrence->executorReference,
            ));
            $runIds[] = $run->id;
        }

        return $runIds;
    }
}
