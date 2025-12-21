<?php

namespace Laravilt\Panel\Concerns;

use Laravilt\Plugins\Contracts\Plugin;

trait HasPlugins
{
    /**
     * Registered plugins.
     *
     * @var array<string, Plugin>
     */
    protected array $plugins = [];

    /**
     * Register a single plugin.
     */
    public function plugin(Plugin $plugin): static
    {
        $this->plugins[$plugin->getId()] = $plugin;

        return $this;
    }

    /**
     * Register multiple plugins.
     *
     * @param  array<Plugin>  $plugins
     */
    public function plugins(array $plugins): static
    {
        foreach ($plugins as $plugin) {
            $this->plugin($plugin);
        }

        return $this;
    }

    /**
     * Get all registered plugins.
     *
     * @return array<string, Plugin>
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Get a specific plugin by ID.
     */
    public function getPlugin(string $id): ?Plugin
    {
        return $this->plugins[$id] ?? null;
    }

    /**
     * Check if a plugin is registered.
     */
    public function hasPlugin(string $id): bool
    {
        return isset($this->plugins[$id]);
    }

    /**
     * Register all plugins with the panel.
     */
    protected function registerPlugins(): void
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->isEnabled()) {
                // Bind the configured plugin instance to the container
                // so resources can access it via app(PluginClass::class)
                app()->instance(get_class($plugin), $plugin);

                $plugin->register($this);
            }
        }
    }

    /**
     * Boot all plugins for the panel.
     */
    protected function bootPlugins(): void
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->isEnabled()) {
                $plugin->boot($this);
            }
        }
    }
}
