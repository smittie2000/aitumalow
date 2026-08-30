<?php

declare(strict_types=1);

namespace Aitumalow\Services;

use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\Models\Workflow;

final class UnscopedExecutionScopeResolver implements ExecutionScopeResolver
{
    public function resolve(Workflow $workflow): ExecutionScope
    {
        return new ExecutionScope;
    }
}
