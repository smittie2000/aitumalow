<?php

declare(strict_types=1);

namespace Aitumalow;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class AitumalowPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'aitumalow';
    }

    public function register(Panel $panel): void
    {
        // The host panel owns its pages and decides where to embed the editor.
    }

    public function boot(Panel $panel): void
    {
        // Editor assets are registered lazily by the package service provider.
    }
}
