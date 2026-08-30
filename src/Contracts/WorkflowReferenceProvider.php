<?php

declare(strict_types=1);

namespace Aitumalow\Contracts;

use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\ReferenceOption;

interface WorkflowReferenceProvider
{
    /** @return iterable<ReferenceOption> */
    public function options(ExecutionScope $scope): iterable;

    public function allows(string $reference, ExecutionScope $scope): bool;
}
