<?php

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
use Aitumalow\Runtime\DurableWorkflowRuntime;
use Aitumalow\Runtime\ScheduleSynchronizer;
use Aitumalow\Services\WorkflowService;

it('resets execution services between application lifecycles', function () {
    $services = [
        ExpressionEvaluator::class,
        NodeRunner::class,
        GraphValidator::class,
        DurableWorkflowRuntime::class,
        ScheduleSynchronizer::class,
        WorkflowService::class,
    ];

    $firstLifecycle = [];

    foreach ($services as $service) {
        $firstLifecycle[$service] = app($service);
    }

    app()->forgetScopedInstances();

    foreach ($services as $service) {
        expect(app($service))->not->toBe($firstLifecycle[$service]);
    }
});

it('keeps only application configuration catalogs across lifecycles', function () {
    $catalogs = [
        NodeRegistry::class,
        PluginRegistry::class,
        PluginManager::class,
        ExpressionFunctionRegistry::class,
        NodeMiddlewareRegistry::class,
        ReferenceProviderRegistry::class,
    ];

    $firstLifecycle = [];

    foreach ($catalogs as $catalog) {
        $firstLifecycle[$catalog] = app($catalog);
    }

    app()->forgetScopedInstances();

    foreach ($catalogs as $catalog) {
        expect(app($catalog))->toBe($firstLifecycle[$catalog]);
    }
});

it('shares plugin expression definitions without sharing evaluator state', function () {
    app(ExpressionFunctionRegistry::class)->register('double', fn (int $value): int => $value * 2);
    app(ExpressionFunctionRegistry::class)->register('upper', fn (string $value): string => "plugin:{$value}");

    $firstEvaluator = app(ExpressionEvaluatorInterface::class);

    expect($firstEvaluator->resolve('{{ double(21) }}', []))
        ->toBe(42)
        ->and($firstEvaluator->resolve("{{ upper('override') }}", []))->toBe('plugin:override');

    app()->forgetScopedInstances();

    $nextEvaluator = app(ExpressionEvaluatorInterface::class);

    expect($nextEvaluator)
        ->not->toBe($firstEvaluator)
        ->and($nextEvaluator)->toBe(app(ExpressionEvaluator::class))
        ->and($nextEvaluator->resolve('{{ double(22) }}', []))->toBe(44);
});
