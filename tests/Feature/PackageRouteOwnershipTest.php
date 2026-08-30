<?php

declare(strict_types=1);

namespace Aitumalow\Tests\Feature;

use Aitumalow\AitumalowServiceProvider;
use Orchestra\Testbench\TestCase;

final class PackageRouteOwnershipTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AitumalowServiceProvider::class];
    }

    public function test_installing_the_package_does_not_register_http_routes(): void
    {
        $this->get('/workflow-engine/workflows')->assertNotFound();
        $this->post('/workflow-webhook/example')->assertNotFound();
    }
}
