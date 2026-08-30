<?php

namespace Aitumalow\Enums;

enum AggregateFunction: string
{
    case Sum = 'sum';
    case Count = 'count';
    case Avg = 'avg';
    case Min = 'min';
    case Max = 'max';

    /** @param array<int, int|float> $values */
    public function compute(array $values): int|float|null
    {
        return match ($this) {
            self::Sum => array_sum($values),
            self::Count => count($values),
            self::Avg => count($values) > 0 ? array_sum($values) / count($values) : 0,
            self::Min => ! empty($values) ? min($values) : null,
            self::Max => ! empty($values) ? max($values) : null,
        };
    }
}
