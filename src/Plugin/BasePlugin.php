<?php

namespace Aitumalow\Plugin;

use Aitumalow\Contracts\PluginInterface;

abstract class BasePlugin implements PluginInterface
{
    public function boot(PluginContext $context): void
    {
        //
    }

    public function editorScripts(): array
    {
        return [];
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
