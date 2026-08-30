<?php

declare(strict_types=1);

namespace Aitumalow\Exceptions;

use Aitumalow\Models\WorkflowCommand;
use RuntimeException;

final class WorkflowCommandDidNotComplete extends RuntimeException
{
    public function __construct(public readonly WorkflowCommand $command)
    {
        parent::__construct(
            $command->error_message
                ?? "Workflow command {$command->id} did not finish applying.",
        );
    }
}
