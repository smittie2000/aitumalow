<?php

namespace Aitumalow\Events;

use Aitumalow\Models\WorkflowNodeRun;
use Illuminate\Foundation\Events\Dispatchable;

class NodeExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly WorkflowNodeRun $nodeRun,
    ) {}
}
