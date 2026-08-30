<?php

namespace Aitumalow\Plugin;

use Aitumalow\Contracts\PluginInterface;
use Aitumalow\Exceptions\PluginException;

class PluginRegistry
{
    /** @var array<string, PluginInterface> */
    private array $plugins = [];

    private bool $booted = false;

    public function add(PluginInterface $plugin): void
    {
        $id = $plugin->getId();

        if (isset($this->plugins[$id])) {
            throw PluginException::alreadyRegistered($id);
        }

        $this->plugins[$id] = $plugin;
    }

    public function has(string $id): bool
    {
        return isset($this->plugins[$id]);
    }

    public function get(string $id): ?PluginInterface
    {
        return $this->plugins[$id] ?? null;
    }

    /** @return array<string, PluginInterface> */
    public function all(): array
    {
        return $this->plugins;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function markBooted(): void
    {
        $this->booted = true;
    }
}
