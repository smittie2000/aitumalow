<?php

declare(strict_types=1);

namespace Aitumalow\Contracts;

use Aitumalow\DTOs\ScheduledWorkflowOccurrence;
use Carbon\CarbonImmutable;

/** Host-owned discovery adapter invoked by an Aitumalow-owned durable schedule. */
interface ScheduledSubjectSource
{
    public function key(): string;

    public function subjectType(): string;

    /** @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function validateConfiguration(array $configuration): array;

    /** @param array<string, mixed> $configuration */
    public function cron(array $configuration): string;

    /** @param array<string, mixed> $configuration */
    public function timezone(array $configuration): string;

    /**
     * @param  array<string, mixed>  $configuration
     * @return iterable<ScheduledWorkflowOccurrence>
     */
    public function occurrences(array $configuration, CarbonImmutable $scheduledAt): iterable;
}
