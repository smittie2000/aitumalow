<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

final readonly class WorkflowState
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $key,
        public string $label,
        public array $metadata,
    ) {}
}
