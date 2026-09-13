<?php

declare(strict_types=1);

use Composer\InstalledVersions;

it('keeps Durable Workflow imports inside Aitumalow runtime adapters', function (): void {
    $sourceRoot = dirname(__DIR__, 2).'/src';
    $violations = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        if (str_starts_with($path, $sourceRoot.'/Runtime/')
            || str_starts_with($path, $sourceRoot.'/Testing/')) {
            continue;
        }

        $contents = file_get_contents($path);
        if (is_string($contents) && preg_match('/^use Workflow\\\\/m', $contents)) {
            $violations[] = str_replace($sourceRoot.'/', '', $path);
        }
    }

    expect($violations)->toBeEmpty();
});

it('uses only Durable public contracts in production runtime adapters', function (): void {
    $runtimeRoot = dirname(__DIR__, 2).'/src/Runtime';
    $violations = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtimeRoot));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if (is_string($contents) && preg_match('/^use Workflow\\\\V2\\\\(?:Jobs|Models)\\\\/m', $contents)) {
            $violations[] = $file->getFilename();
        }
    }

    expect($violations)->toBeEmpty();
});

it('depends on the stable Durable Workflow 2 release line', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/composer.json');
    if (! is_string($contents)) {
        throw new RuntimeException('Unable to read composer.json.');
    }

    $composer = json_decode(
        $contents,
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $version = InstalledVersions::getVersion('durable-workflow/workflow');

    expect($composer['require']['durable-workflow/workflow'] ?? null)->toBe('^2.0.14')
        ->and($version)->not->toBeNull()
        ->and(version_compare((string) $version, '2.0.14', '>='))->toBeTrue()
        ->and(version_compare((string) $version, '3.0.0', '<'))->toBeTrue();
});
