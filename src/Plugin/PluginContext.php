<?php

namespace Aitumalow\Plugin;

use Aitumalow\Contracts\NodeMiddlewareInterface;
use Aitumalow\Contracts\ScheduledSubjectSource;
use Aitumalow\Contracts\WorkflowReferenceProvider;
use Aitumalow\Contracts\WorkflowSubject;
use Aitumalow\Registry\ExpressionFunctionRegistry;
use Aitumalow\Registry\NodeMiddlewareRegistry;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Registry\ReferenceProviderRegistry;
use Aitumalow\Registry\ScheduledSubjectSourceRegistry;
use Aitumalow\Registry\WorkflowSubjectRegistry;

class PluginContext
{
    public function __construct(
        private readonly NodeRegistry $nodeRegistry,
        private readonly NodeMiddlewareRegistry $middlewareRegistry,
        private readonly ExpressionFunctionRegistry $expressionFunctions,
        private readonly ReferenceProviderRegistry $referenceProviders,
        private readonly WorkflowSubjectRegistry $workflowSubjects,
        private readonly ScheduledSubjectSourceRegistry $scheduledSources,
    ) {}

    /**
     * Register a single node class.
     * The class must have the #[WorkflowNode] attribute.
     *
     * @param  class-string  $class
     */
    public function registerNode(string $class): void
    {
        $this->nodeRegistry->registerClass($class);
    }

    /**
     * Register a custom expression function.
     */
    public function registerExpressionFunction(string $name, callable $fn): void
    {
        $this->expressionFunctions->register($name, $fn);
    }

    /**
     * Register node execution middleware.
     *
     * @param  class-string<NodeMiddlewareInterface>  $middleware
     */
    public function registerMiddleware(string $middleware): void
    {
        $this->middlewareRegistry->register($middleware);
    }

    /** @param class-string<WorkflowReferenceProvider> $provider */
    public function registerReferenceProvider(string $source, string $provider): void
    {
        $this->referenceProviders->register($source, $provider);
    }

    /** @param class-string<WorkflowSubject>|WorkflowSubject $subject */
    public function registerSubject(string|WorkflowSubject $subject): void
    {
        $this->workflowSubjects->register($subject);
    }

    /** @param class-string<ScheduledSubjectSource>|ScheduledSubjectSource $source */
    public function registerScheduledSource(string|ScheduledSubjectSource $source): void
    {
        $this->scheduledSources->register($source);
    }
}
