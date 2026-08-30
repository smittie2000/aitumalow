<?php

namespace Aitumalow\Contracts;

use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Closure;

interface NodeMiddlewareInterface
{
    /**
     * Handle node execution, optionally modifying input/output or adding behavior.
     *
     * @param  array<string, mixed>  $config
     * @param  Closure(NodeInterface, NodeInput, array<string, mixed>): NodeOutput  $next
     */
    public function handle(
        NodeInterface $node,
        NodeInput $input,
        array $config,
        Closure $next,
    ): NodeOutput;
}
