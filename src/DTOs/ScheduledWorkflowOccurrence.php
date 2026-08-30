<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

use InvalidArgumentException;

final readonly class ScheduledWorkflowOccurrence
{
    /** @param array<int, array<string, mixed>> $payload */
    public function __construct(
        public string $subjectReference,
        public string $idempotencyKey,
        public array $payload = [],
        public ?string $executorReference = null,
        public ?ExecutionScope $scope = null,
    ) {
        if ($subjectReference === '' || mb_strlen($subjectReference) > 191) {
            throw new InvalidArgumentException('Scheduled subject references must contain between 1 and 191 characters.');
        }
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 191) {
            throw new InvalidArgumentException('Scheduled occurrence keys must contain between 1 and 191 characters.');
        }
    }
}
