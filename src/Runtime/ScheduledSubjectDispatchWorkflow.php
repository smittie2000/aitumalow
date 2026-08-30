<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Workflow\V2\Attributes\Type;
use Workflow\V2\Support\ActivityOptions;
use Workflow\V2\Workflow;

#[Type('aitumalow.subject_schedule.v1')]
final class ScheduledSubjectDispatchWorkflow extends Workflow
{
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
    ): array {
        return Workflow::activity(
            DiscoverScheduledSubjectsActivity::class,
            new ActivityOptions(maxAttempts: 3, backoff: 1),
            $workflowId,
            $revisionId,
            $sourceKey,
            $configuration,
            $scope,
            Workflow::now()->toISOString(),
        );
    }
}
