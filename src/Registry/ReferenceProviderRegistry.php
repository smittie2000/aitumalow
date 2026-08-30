<?php

declare(strict_types=1);

namespace Aitumalow\Registry;

use Aitumalow\Contracts\WorkflowReferenceProvider;
use Aitumalow\DTOs\ExecutionScope;

final class ReferenceProviderRegistry
{
    private const string SOURCE_PATTERN = '/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_-]*)+\z/';

    /** @var array<string, class-string<WorkflowReferenceProvider>> */
    private array $providers = [];

    /** @param class-string $provider */
    public function register(string $source, string $provider): void
    {
        if (! preg_match(self::SOURCE_PATTERN, $source)) {
            throw new \InvalidArgumentException(
                "Reference source [{$source}] must be a lowercase namespaced key such as app.records.readable.",
            );
        }

        if (! is_a($provider, WorkflowReferenceProvider::class, true)) {
            throw new \InvalidArgumentException("{$provider} must implement ".WorkflowReferenceProvider::class.'.');
        }

        if (isset($this->providers[$source])) {
            throw new \InvalidArgumentException("Reference source [{$source}] is already registered.");
        }

        $this->providers[$source] = $provider;
    }

    public function has(string $source): bool
    {
        return isset($this->providers[$source]);
    }

    /** @return array<int, array{value: string, label: string, description: string|null}> */
    public function options(string $source, ExecutionScope $scope): array
    {
        $options = [];

        foreach ($this->resolve($source)->options($scope) as $option) {
            $options[] = $option->toArray();
        }

        return $options;
    }

    public function allows(string $source, string $reference, ExecutionScope $scope): bool
    {
        return $this->resolve($source)->allows($reference, $scope);
    }

    private function resolve(string $source): WorkflowReferenceProvider
    {
        $provider = $this->providers[$source] ?? null;

        if ($provider === null) {
            throw new \InvalidArgumentException("Reference source [{$source}] is not registered.");
        }

        return app($provider);
    }
}
