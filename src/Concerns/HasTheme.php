<?php

namespace Laravilt\Panel\Concerns;

use Closure;
use Laravilt\Panel\FontProviders\FontProvider;
use Laravilt\Panel\FontProviders\GoogleFontProvider;

trait HasTheme
{
    protected string|FontProvider|Closure|null $font = null;

    protected bool|Closure $darkMode = true;

    protected array|Closure $customCss = [];

    protected array|Closure $customJs = [];

    /**
     * Set the panel font.
     *
     * Accepts a string (font family name), a FontProvider instance, or a closure.
     *
     * Examples:
     * - ->font('Inter')
     * - ->font(GoogleFontProvider::make('Inter')->weights([400, 500, 600, 700]))
     */
    public function font(string|FontProvider|Closure|null $font): static
    {
        $this->font = $font;

        return $this;
    }

    /**
     * Get the panel font.
     * Returns null if no custom font is set.
     */
    public function getFont(): ?FontProvider
    {
        $font = $this->evaluate($this->font);

        if ($font === null) {
            return null;
        }

        // If it's already a FontProvider, return it
        if ($font instanceof FontProvider) {
            return $font;
        }

        // If it's a string, wrap it in a GoogleFontProvider
        if (is_string($font)) {
            return GoogleFontProvider::make($font);
        }

        return null;
    }

    /**
     * Get the font family name for CSS.
     */
    public function getFontFamily(): ?string
    {
        $font = $this->getFont();

        return $font?->getFamily();
    }

    /**
     * Get the font URL for loading.
     */
    public function getFontUrl(): ?string
    {
        $font = $this->getFont();

        return $font?->getUrl();
    }

    /**
     * Check if a custom font is configured.
     */
    public function hasFont(): bool
    {
        return $this->getFont() !== null;
    }

    /**
     * Get font data as array for sharing with frontend.
     */
    public function getFontData(): ?array
    {
        $font = $this->getFont();

        return $font?->toArray();
    }

    /**
     * Enable/disable dark mode.
     */
    public function darkMode(bool|Closure $enabled = true): static
    {
        $this->darkMode = $enabled;

        return $this;
    }

    /**
     * Check if dark mode is enabled.
     */
    public function hasDarkMode(): bool
    {
        return $this->evaluate($this->darkMode);
    }

    /**
     * Add custom CSS files.
     */
    public function customCss(array|Closure $files): static
    {
        $this->customCss = $files;

        return $this;
    }

    /**
     * Get custom CSS files.
     */
    public function getCustomCss(): array
    {
        return $this->evaluate($this->customCss);
    }

    /**
     * Add custom JS files.
     */
    public function customJs(array|Closure $files): static
    {
        $this->customJs = $files;

        return $this;
    }

    /**
     * Get custom JS files.
     */
    public function getCustomJs(): array
    {
        return $this->evaluate($this->customJs);
    }
}
