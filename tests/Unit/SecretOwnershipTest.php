<?php

declare(strict_types=1);

use Aitumalow\Registry\NodeRegistry;

it('ships no package secret storage or resolver types', function (): void {
    expect(class_exists('Aitumalow\\Models\\WorkflowCredential'))->toBeFalse()
        ->and(class_exists('Aitumalow\\Credentials\\CredentialTypeRegistry'))->toBeFalse()
        ->and(class_exists('Aitumalow\\Credentials\\CredentialResolutionMiddleware'))->toBeFalse();
});

it('ships no secret-shaped built-in configuration fields', function (): void {
    $fieldTypes = collect(app(NodeRegistry::class)->all())
        ->flatMap(fn (array $definition): array => $definition['config_schema'])
        ->pluck('type');

    expect($fieldTypes)
        ->not->toContain('credential')
        ->not->toContain('password');
});
