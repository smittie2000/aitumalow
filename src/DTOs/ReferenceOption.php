<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

final readonly class ReferenceOption
{
    public function __construct(
        public string $value,
        public string $label,
        public ?string $description = null,
    ) {}

    /** @return array{value: string, label: string, description: string|null} */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'description' => $this->description,
        ];
    }
}
