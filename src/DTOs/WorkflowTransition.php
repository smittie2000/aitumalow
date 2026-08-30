<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

final readonly class WorkflowTransition
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $key,
        public string $label,
        public string $fromState,
        public ?string $targetState,
        public array $metadata,
    ) {}
}
