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
        ->toContain('workflowId: 42')
        ->toContain('/custom-workflow-api')
        ->toContain('height: 60vh');
});
