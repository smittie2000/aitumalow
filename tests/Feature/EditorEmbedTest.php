<?php

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;

it('registers the compiled editor assets with Filament', function () {
    expect(FilamentAsset::getScriptSrc('editor', package: 'aitumalow/aitumalow'))
        ->toContain('/js/aitumalow/aitumalow/editor.js')
        ->and(FilamentAsset::getStyleHref('editor', package: 'aitumalow/aitumalow'))->toContain('/css/aitumalow/aitumalow/editor.css');
});

it('renders a host-owned editor mount point', function () {
    $html = Blade::render(<<<'BLADE'
        <x-aitumalow::editor
            :workflow-id="42"
            api-base-url="/custom-workflow-api"
            height="60vh"
        />
        BLADE);

    expect($html)
        ->toContain('window.AitumalowEditor.mountAitumalowEditor')
        ->toContain('data-dispatch="aitumalow-editor-loaded"')
        ->toContain('x-on:aitumalow-editor-loaded-js.window="mountEditor($refs.target)"')
        ->toContain('this.editor || !window.AitumalowEditor')
        ->toContain('workflowId: 42')
        ->toContain('/custom-workflow-api')
        ->toContain('height: 60vh');
});

it('keeps editor buttons from submitting a host Filament form', function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../../ui/src'),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'tsx') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            throw new RuntimeException("Unable to read editor source file [{$file->getPathname()}].");
        }
        preg_match_all('/<button\\b[^>]*>/s', $source, $buttons);

        expect($buttons[0])->each->toContain('type="button"');
    }
});
