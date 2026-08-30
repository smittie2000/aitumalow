<?php

namespace Aitumalow\DTOs;

readonly class NodeInput
{
    /**
     * @param  array<int, array<string, mixed>>  $items  Data items flowing into this node.
     * @param  ExecutionContext  $context  Full run context.
     * @param  string  $port  Which input port these items arrived on.
     */
    public function __construct(
        public array $items,
        public ExecutionContext $context,
        public string $port = 'main',
    ) {}
}
