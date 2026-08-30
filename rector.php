<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withRootFiles()
    ->withPhpSets()
    ->withSetProviders(LaravelSetProvider::class)
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    ->withComposerBased(
        phpunit: true,
        laravel: true,
    )
    ->withoutParallel();
