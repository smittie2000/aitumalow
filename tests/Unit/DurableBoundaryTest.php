<?php

declare(strict_types=1);

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
