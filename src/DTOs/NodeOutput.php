<?php

namespace Aitumalow\DTOs;

readonly class NodeOutput
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $portItems  Map of port name => items.
     */
    public function __construct(
        public array $portItems = [],
    ) {}

    /**
     * Create output with items on the 'main' port.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function main(array $items): self
    {
        return new self(['main' => $items]);
    }

    /**
     * Create output with items on a single named port.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function port(string $port, array $items): self
    {
        return new self([$port => $items]);
    }

    /**
     * Create output with items on multiple ports.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $portItems
     */
    public static function ports(array $portItems): self
    {
        return new self($portItems);
    }

    /**
     * Get items for a given port.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(string $port = 'main'): array
    {
        return $this->portItems[$port] ?? [];
    }

    /**
     * Check if a port has any items.
     */
    public function hasItems(string $port = 'main'): bool
    {
        return ! empty($this->portItems[$port]);
    }
}
