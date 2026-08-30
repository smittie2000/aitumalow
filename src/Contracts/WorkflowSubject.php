<?php

declare(strict_types=1);

namespace Aitumalow\Contracts;

use Aitumalow\DTOs\ExecutionScope;

/**
 * Host-owned adapter for an opaque business subject used by a workflow.
 *
 * Implementations resolve and authorize host records. Aitumalow persists only
 * the stable adapter key, opaque reference, bounded context, and freshness.
 */
interface WorkflowSubject
{
    public function key(): string;

    public function resolve(string $reference): mixed;

    public function reference(mixed $subject): string;

    public function canStart(ExecutionScope $scope, mixed $subject): bool;

    /** @param array<string, mixed> $payload */
    public function canCommand(ExecutionScope $scope, mixed $subject, string $command, array $payload): bool;

    /** @return array<string, mixed> */
    public function context(mixed $subject): array;

    public function freshness(mixed $subject): ?string;

    /** @return list<string> */
    public function allowedCapabilities(mixed $subject): array;
}
