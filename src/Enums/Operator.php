<?php

namespace Aitumalow\Enums;

enum Operator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case GreaterOrEqual = 'greater_or_equal';
    case LessOrEqual = 'less_or_equal';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';

    public function evaluate(mixed $fieldValue, mixed $compareValue = null): bool
    {
        return match ($this) {
            self::Equals => $fieldValue == $compareValue,
            self::NotEquals => $fieldValue != $compareValue,
            self::Contains => str_contains((string) $fieldValue, (string) $compareValue),
            self::NotContains => ! str_contains((string) $fieldValue, (string) $compareValue),
            self::GreaterThan => $fieldValue > $compareValue,
            self::LessThan => $fieldValue < $compareValue,
            self::GreaterOrEqual => $fieldValue >= $compareValue,
            self::LessOrEqual => $fieldValue <= $compareValue,
            self::IsEmpty => empty($fieldValue),
            self::IsNotEmpty => ! empty($fieldValue),
            self::StartsWith => str_starts_with((string) $fieldValue, (string) $compareValue),
            self::EndsWith => str_ends_with((string) $fieldValue, (string) $compareValue),
        };
    }
}
