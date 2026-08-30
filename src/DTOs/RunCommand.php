<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

use InvalidArgumentException;

final readonly class RunCommand
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $name,
        public string $idempotencyKey,
        public array $payload = [],
        public ?ExecutionScope $scope = null,
        public ?string $expectedState = null,
    ) {
        if (! preg_match('/\A[a-z][a-z0-9_-]*\z/', $name) || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Workflow command names must be bounded lowercase stable keys.');
        }

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 191) {
            throw new InvalidArgumentException('Workflow command idempotency keys must contain between 1 and 191 characters.');
        }

        if ($expectedState !== null && ($expectedState === '' || mb_strlen($expectedState) > 100)) {
            throw new InvalidArgumentException('Expected workflow states must contain between 1 and 100 characters.');
        }
    }
}
