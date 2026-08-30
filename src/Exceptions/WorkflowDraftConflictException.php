<?php

declare(strict_types=1);

namespace Aitumalow\Exceptions;

final class WorkflowDraftConflictException extends WorkflowException
{
    public function __construct()
    {
        parent::__construct('The workflow draft changed after it was read. Fetch it again before saving.');
    }
}
