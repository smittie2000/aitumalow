<?php

namespace Aitumalow\Contracts;

use Aitumalow\DTOs\WorkflowContext;

interface WorkflowAction
{
    /** @return array<int, array<string, mixed>> */
    public function schema(): array;

    /** @return array<int, array<string, mixed>> */
    public function outputSchema(): array;

    /** @return array<string, mixed> */
    public function handle(WorkflowContext $context): array;
}
