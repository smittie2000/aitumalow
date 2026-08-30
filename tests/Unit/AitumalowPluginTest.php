<?php

declare(strict_types=1);

use Aitumalow\AitumalowPlugin;
use Filament\Panel;

it('provides the Filament plugin entry point', function (): void {
    $plugin = AitumalowPlugin::make();

    expect($plugin->getId())->toBe('aitumalow');
});

it('registers explicitly with a Filament panel', function (): void {
    $plugin = AitumalowPlugin::make();
    $panel = Panel::make()->plugin($plugin);

    expect($panel->hasPlugin('aitumalow'))->toBeTrue()
        ->and($panel->getPlugin('aitumalow'))->toBe($plugin);
});
