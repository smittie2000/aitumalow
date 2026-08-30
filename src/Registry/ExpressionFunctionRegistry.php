<?php

namespace Aitumalow\Registry;

class ExpressionFunctionRegistry
{
    /** @var array<string, callable> */
    private array $functions = [];

    public function register(string $name, callable $function): void
    {
        $this->functions[$name] = $function;
    }

    public function get(string $name): ?callable
    {
        return $this->functions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    /** @return array<string, callable> */
    public function all(): array
    {
        return $this->functions;
    }
}
