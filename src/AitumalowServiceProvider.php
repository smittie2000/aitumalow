<?php

declare(strict_types=1);

namespace Aitumalow;

use Aitumalow\Console\Commands\ValidateWorkflowCommand;
use Aitumalow\Contracts\ExecutionScopeResolver;
use Aitumalow\Contracts\ExpressionEvaluatorInterface;
use Aitumalow\Engine\ExpressionEvaluator;
use Aitumalow\Engine\GraphValidator;
use Aitumalow\Engine\NodeRunner;
use Aitumalow\Plugin\PluginManager;
use Aitumalow\Plugin\PluginRegistry;
use Aitumalow\Registry\ExpressionFunctionRegistry;
use Aitumalow\Registry\NodeMiddlewareRegistry;
use Aitumalow\Registry\NodeRegistry;
use Aitumalow\Registry\ReferenceProviderRegistry;
use Aitumalow\Registry\ScheduledSubjectSourceRegistry;
use Aitumalow\Registry\WorkflowSubjectRegistry;
use Aitumalow\Runtime\RuntimeProjectionListener;
use Aitumalow\Services\UnscopedExecutionScopeResolver;
use Aitumalow\Services\WorkflowNodeConfigValidator;
use Aitumalow\Services\WorkflowService;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AitumalowServiceProvider extends PackageServiceProvider
{
    public static string $name = 'aitumalow';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                ValidateWorkflowCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Application-lifetime definition catalogs. These contain only node,
        // plugin, expression-function, and middleware definitions registered
        // while the application boots.
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(PluginRegistry::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(ExpressionFunctionRegistry::class);
        $this->app->singleton(NodeMiddlewareRegistry::class);
        $this->app->singleton(ReferenceProviderRegistry::class);
        $this->app->singleton(WorkflowSubjectRegistry::class);
        $this->app->singleton(ScheduledSubjectSourceRegistry::class);
        $this->app->bindIf(ExecutionScopeResolver::class, UnscopedExecutionScopeResolver::class);

        // Runtime services may hold parser or execution state, so Octane and
        // queue workers must receive fresh instances for each lifecycle.
        $this->app->scoped(ExpressionEvaluator::class, fn ($app) => new ExpressionEvaluator(
            $app->make(ExpressionFunctionRegistry::class),
        ));
        $this->app->alias(ExpressionEvaluator::class, ExpressionEvaluatorInterface::class);

        $this->app->scoped(NodeRunner::class, fn ($app) => new NodeRunner(
            $app->make(NodeMiddlewareRegistry::class),
        ));

        $this->app->scoped(GraphValidator::class, fn ($app) => new GraphValidator(
            $app->make(NodeRegistry::class),
            $app->make(WorkflowNodeConfigValidator::class),
            $app->make(ExecutionScopeResolver::class),
        ));

        $this->app->scoped(WorkflowService::class, fn ($app) => new WorkflowService(
            runtime: $app->make(Runtime\DurableWorkflowRuntime::class),
            validator: $app->make(GraphValidator::class),
            configValidator: $app->make(WorkflowNodeConfigValidator::class),
            scopeResolver: $app->make(ExecutionScopeResolver::class),
            schedules: $app->make(Runtime\ScheduleSynchronizer::class),
            snapshots: $app->make(Runtime\WorkflowSnapshot::class),
            subjects: $app->make(WorkflowSubjectRegistry::class),
        ));
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'aitumalow-migrations');
        }

        FilamentAsset::register([
            Js::make('editor', __DIR__.'/../ui/dist/embed/aitumalow-editor.js')->loadedOnRequest(),
            Css::make('editor', __DIR__.'/../ui/dist/embed/aitumalow-editor.css')->loadedOnRequest(),
        ], package: 'aitumalow/aitumalow');

        $this->registerBuiltInNodes();
        $this->registerConfigPlugins();
        $this->bootPlugins();
        $this->registerListeners();
    }

    private function registerListeners(): void
    {
        $this->app->booted(function () {
            RuntimeProjectionListener::register();
        });
    }

    private function registerConfigPlugins(): void
    {
        $manager = $this->app->make(PluginManager::class);

        foreach (config('aitumalow.plugins', []) as $pluginClass) {
            if (is_string($pluginClass) && class_exists($pluginClass)) {
                $manager->plugin($pluginClass::make());
            }
        }
    }

    private function bootPlugins(): void
    {
        $this->app->make(PluginManager::class)->bootPlugins();
    }

    private function registerBuiltInNodes(): void
    {
        $registry = $this->app->make(NodeRegistry::class);

        foreach ([
            Nodes\Annotations\StickyNote::class,
            Nodes\Conditions\IfCondition::class,
            Nodes\Conditions\SwitchCondition::class,
            Nodes\Controls\DelayControl::class,
            Nodes\Controls\LoopControl::class,
            Nodes\Controls\MergeControl::class,
            Nodes\Controls\WaitResumeControl::class,
            Nodes\Transformers\ParseDataTransformer::class,
            Nodes\Transformers\SetFieldsTransformer::class,
            Nodes\Triggers\ManualTrigger::class,
            Nodes\Triggers\HostScheduleTrigger::class,
            Nodes\Triggers\ScheduleTrigger::class,
            Nodes\Utilities\AggregateUtility::class,
            Nodes\Utilities\FilterUtility::class,
        ] as $node) {
            $registry->registerClass($node);
        }
    }
}
