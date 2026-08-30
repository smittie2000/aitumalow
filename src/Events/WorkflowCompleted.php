<?php

namespace Aitumalow\Events;

use Aitumalow\Models\WorkflowRun;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowCompleted
{
    use Dispatchable;

    /** @param array<int, array<string, array<int, array<string, mixed>>>> $outputData */
    public function __construct(
        public readonly WorkflowRun $run,
        public readonly array $outputData = [],
    ) {}
}
