<?php

namespace Aitumalow\Enums;

enum NodeType: string
{
    case Trigger = 'trigger';
    case Action = 'action';
    case Condition = 'condition';
    case Transformer = 'transformer';
    case Control = 'control';
    case Utility = 'utility';
    case Code = 'code';
    case Annotation = 'annotation';
}
