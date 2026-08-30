<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

use InvalidArgumentException;

final readonly class WorkflowStart
{
    /**
     * @param  array<int, array<string, mixed>>  $payload
     */
    public function __construct(
        public array $payload = [],
        public ?SubjectReference $subject = null,
        public ?ExecutionScope $scope = null,
        public ?string $idempotencyKey = null,
        public ?string $idempotencyScope = null,
        public ?string $executorReference = null,
    ) {
        if ($idempotencyKey !== null && ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 191)) {
            throw new InvalidArgumentException('Workflow idempotency keys must contain between 1 and 191 characters.');
        }

        if ($idempotencyScope !== null && ($idempotencyScope === '' || mb_strlen($idempotencyScope) > 191)) {
            throw new InvalidArgumentException('Workflow idempotency scopes must contain between 1 and 191 characters.');
        }

        if ($idempotencyKey === null && $idempotencyScope !== null) {
            throw new InvalidArgumentException('An idempotency scope requires an idempotency key.');
        }

        if ($executorReference !== null && ($executorReference === '' || mb_strlen($executorReference) > 191)) {
            throw new InvalidArgumentException('Workflow executor references must contain between 1 and 191 characters.');
        }
    }
}
