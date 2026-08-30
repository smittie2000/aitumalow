<?php

namespace Aitumalow\Plugin;

use Aitumalow\Contracts\PluginInterface;
use Aitumalow\Contracts\ScheduledSubjectSource;
use Aitumalow\Contracts\WorkflowReferenceProvider;
use Aitumalow\Contracts\WorkflowSubject;
use Aitumalow\Registry\ExpressionFunctionRegistry;
use Aitumalow\Registry\NodeMiddlewareRegistry;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Registry\ReferenceProviderRegistry;
use Aitumalow\Registry\ScheduledSubjectSourceRegistry;
use Aitumalow\Registry\WorkflowSubjectRegistry;

class PluginManager
{
    private readonly PluginContext $context;

    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly NodeRegistry $nodeRegistry,
        private readonly NodeMiddlewareRegistry $middlewareRegistry,
        private readonly ExpressionFunctionRegistry $expressionFunctions,
        private readonly ReferenceProviderRegistry $referenceProviders,
        private readonly WorkflowSubjectRegistry $workflowSubjects,
        private readonly ScheduledSubjectSourceRegistry $scheduledSources,
    ) {
        $this->context = new PluginContext(
            $this->nodeRegistry,
            $this->middlewareRegistry,
            $this->expressionFunctions,
            $this->referenceProviders,
            $this->workflowSubjects,
            $this->scheduledSources,
        );
    }

    /** @param class-string<ScheduledSubjectSource>|ScheduledSubjectSource $source */
    public function scheduledSource(string|ScheduledSubjectSource $source): void
    {
        $this->scheduledSources->register($source);
    }

    /**
     * Register a plugin. Calls plugin->register() immediately.
     */
    public function plugin(PluginInterface $plugin): void
    {
        $this->registry->add($plugin);
        $plugin->register($this->context);
    }

    /** @param class-string $class */
    public function register(string $class): void
    {
        $this->nodeRegistry->registerClass($class);
    }

    /** @param class-string<WorkflowReferenceProvider> $provider */
    public function reference(string $source, string $provider): void
    {
        $this->referenceProviders->register($source, $provider);
    }

    /** @param class-string<WorkflowSubject>|WorkflowSubject $subject */
    public function subject(string|WorkflowSubject $subject): void
    {
        $this->workflowSubjects->register($subject);
    }

    /**
     * Boot all registered plugins. Called once during ServiceProvider::boot().
     */
    public function bootPlugins(): void
    {
        if ($this->registry->isBooted()) {
            return;
        }

        foreach ($this->registry->all() as $plugin) {
            $plugin->boot($this->context);
        }

        $this->registry->markBooted();
    }

    /**
     * Get the plugin registry.
     */
    public function plugins(): PluginRegistry
    {
        return $this->registry;
    }

    /**
     * Get the plugin context.
     */
    public function context(): PluginContext
    {
        return $this->context;
    }
}
