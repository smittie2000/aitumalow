<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Aitumalow\AitumalowServiceProvider;
use Aitumalow\Tests\Browser\BrowserApplication;
use Aitumalow\Tests\Browser\HostServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Workflow\Providers\WorkflowServiceProvider;

$storage = dirname(__DIR__, 2).'/storage/browser-testing';
foreach (['', '/logs', '/framework/cache', '/framework/sessions', '/framework/views', '/inbox', '/calls'] as $directory) {
    if (! is_dir($storage.$directory)) {
        mkdir($storage.$directory, 0775, true);
    }
}

return BrowserApplication::create(resolvingCallback: function ($app) use ($storage): void {
    $app->useStoragePath($storage);
}, options: ['extra' => [
    'env' => ['DW_V1_ENABLED' => false],
    'providers' => [WorkflowServiceProvider::class, AitumalowServiceProvider::class, McpServiceProvider::class, HostServiceProvider::class],
    'dont-discover' => ['*'],
]]);
