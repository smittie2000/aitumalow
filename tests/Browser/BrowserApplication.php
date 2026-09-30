<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Browser;

use Orchestra\Testbench\Foundation\Application;

final class BrowserApplication extends Application
{
    protected function getEnvironmentSetUp($app): void
    {
        $storage = dirname(__DIR__, 2).'/storage/browser-testing';
        $app['config']->set([
            'app.env' => 'browser-testing',
            'app.debug' => true,
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'database.default' => 'browser',
            'database.connections.browser' => [
                'driver' => 'sqlite', 'database' => $storage.'/database.sqlite',
                'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 10000,
                'journal_mode' => 'WAL',
                // Acquire the writer lock before graph transactions read their snapshot.
                'transaction_mode' => 'IMMEDIATE',
            ],
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'browser',
            'workflows.v2.connection' => 'database',
            'workflows.v2.queue' => 'browser-workflows',
            'aitumalow.queue' => 'browser-workflows',
            'cache.default' => 'array',
            'session.driver' => 'file',
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1026,
                'scheme' => 'smtp', 'timeout' => 5,
            ],
            'mail.from' => ['address' => 'workflow@example.test', 'name' => 'Workflow test area'],
        ]);
    }
}
