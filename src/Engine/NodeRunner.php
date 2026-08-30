<?php

namespace Aitumalow\Engine;

use Aitumalow\Contracts\NodeInterface;
use Aitumalow\Contracts\NodeMiddlewareInterface;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\DTOs\NodeOutput;
use Aitumalow\Registry\NodeMiddlewareRegistry;
use Closure;

class NodeRunner
{
    /** @var array<int, class-string<NodeMiddlewareInterface>|NodeMiddlewareInterface> */
    private array $middleware = [];

    public function __construct(
        private readonly ?NodeMiddlewareRegistry $registeredMiddleware = null,
    ) {}

    /**
     * Add a middleware to the execution pipeline.
     *
     * @param  class-string<NodeMiddlewareInterface>|NodeMiddlewareInterface  $middleware
     */
    public function pushMiddleware(string|NodeMiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /** @param array<string, mixed> $config */
    public function run(
        NodeInterface $node,
        NodeInput $input,
        array $config,
    ): NodeOutput {
        return $this->executeWithMiddleware($node, $input, $config);
    }

    /** @param array<string, mixed> $config */
    private function executeWithMiddleware(
        NodeInterface $node,
        NodeInput $input,
        array $config,
    ): NodeOutput {
        $middleware = [
            ...($this->registeredMiddleware?->all() ?? []),
            ...$this->middleware,
        ];

        if (empty($middleware)) {
            return $node->execute($input, $config);
        }

        $pipeline = array_reduce(
            array_reverse($middleware),
            fn (Closure $next, string|NodeMiddlewareInterface $middleware): Closure => function (NodeInterface $node, NodeInput $input, array $config) use ($middleware, $next): NodeOutput {
                $instance = is_string($middleware) ? app($middleware) : $middleware;

                return $instance->handle($node, $input, $config, $next);
            },
            fn (NodeInterface $node, NodeInput $input, array $config): NodeOutput => $node->execute($input, $config),
        );

        return $pipeline($node, $input, $config);
    }
}
