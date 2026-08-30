<?php

namespace Aitumalow\Registry;

use Aitumalow\Contracts\NodeMiddlewareInterface;

class NodeMiddlewareRegistry
{
    /** @var array<int, class-string<NodeMiddlewareInterface>> */
    private array $middleware = [];

    public function register(string $middleware): void
    {
        if (! is_a($middleware, NodeMiddlewareInterface::class, true)) {
            throw new \InvalidArgumentException("{$middleware} does not implement NodeMiddlewareInterface.");
        }

        $this->middleware[] = $middleware;
    }

    /** @return array<int, class-string<NodeMiddlewareInterface>> */
    public function all(): array
    {
        return $this->middleware;
    }
}
