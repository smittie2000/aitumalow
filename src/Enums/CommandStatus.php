<?php

declare(strict_types=1);

namespace Aitumalow\Enums;

enum CommandStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
