<?php

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

it('does not own an AI runtime or assistant transport', function () {
    $editorRoutes = file_get_contents(__DIR__.'/../../src/Http/EditorApiRoutes.php');
    $provider = file_get_contents(__DIR__.'/../../src/AitumalowServiceProvider.php');
    $composer = file_get_contents(__DIR__.'/../../composer.json');

    expect($editorRoutes)->toBeString()
        ->not->toContain('ai-build')
        ->not->toContain('transcribe')
        ->and($provider)->toBeString()
        ->not->toContain('Laravel\\Ai')
        ->not->toContain('Mcp::web')
        ->not->toContain('Mcp::local')
        ->and($composer)->toBeString()
        ->not->toContain('laravel/ai');
});

it('isolates Laravel MCP imports to the MCP integration namespace', function () {
    $source = Finder::create()
        ->files()
        ->in(__DIR__.'/../../src')
        ->name('*.php');

    foreach ($source as $file) {
        $contents = $file->getContents();
        $relativePath = str_replace('\\', '/', $file->getRelativePathname());

        expect($contents)->not->toContain('Laravel\\Ai\\');

        if (Str::contains($contents, 'Laravel\\Mcp\\')) {
            expect($relativePath)->toStartWith('Mcp/');
        }
    }
});
