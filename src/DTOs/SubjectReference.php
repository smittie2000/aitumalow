<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

use InvalidArgumentException;

final readonly class SubjectReference
{
    public function __construct(
        public string $type,
        public string $reference,
    ) {
        if ($type === '' || mb_strlen($type) > 100) {
            throw new InvalidArgumentException('A bounded workflow subject type is required.');
        }

        if ($reference === '' || mb_strlen($reference) > 191) {
            throw new InvalidArgumentException('A bounded workflow subject reference is required.');
        }
    }
}
