<?php

namespace Aitumalow\DTOs;

use Illuminate\Support\Arr;

final readonly class WorkflowContext
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $config
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        private array $input,
        private array $config,
        private array $items,
        public ExecutionContext $execution,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        if (Arr::has($this->input, $key)) {
            return data_get($this->input, $key);
        }

        return data_get($this->config, $key, $default);
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    /** @return array<int, array<string, mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    public function scope(): ExecutionScope
    {
        return $this->execution->scope;
    }

    /**
     * Stable across Durable retries of this capability invocation.
     *
     * Host actions can use this as an idempotency key when producing side
     * effects. It is null only when a node is invoked outside Durable runtime.
     */
    public function activityId(): ?string
    {
        return $this->execution->durableActivityId;
    }

    public function subjectType(): ?string
    {
        return $this->execution->subjectType;
    }

    public function subjectReference(): ?string
    {
        return $this->execution->subjectReference;
    }

    /** @return array<string, mixed> */
    public function subjectContext(): array
    {
        return $this->execution->subjectContext;
    }

    public function subjectFreshness(): ?string
    {
        return $this->execution->subjectFreshness;
    }

    public function executorReference(): ?string
    {
        return $this->execution->executorReference;
    }
}
