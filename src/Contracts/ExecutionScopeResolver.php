<?php

declare(strict_types=1);

namespace Aitumalow\Contracts;

use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\Models\Workflow;

interface ExecutionScopeResolver
{
    public function resolve(Workflow $workflow): ExecutionScope;
}
