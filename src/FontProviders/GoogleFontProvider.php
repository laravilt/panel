<?php

namespace Laravilt\Panel\FontProviders;

class GoogleFontProvider implements FontProvider
{
    protected string $family;

    protected array $weights = [400, 500, 600, 700];

    protected ?string $display = 'swap';

    protected array $subsets = ['latin'];

    /**
     * Create a new Google Font provider instance.
     */
    public function __construct(string $family)
    {
        $this->family = $family;
    }

    /**
     * Create a new Google Font provider instance.
     */
    public static function make(string $family): static
    {
        return new static($family);
    }

    /**
     * Set the font weights to load.
     */
    public function weights(array $weights): static
    {
        $this->weights = $weights;

        return $this;
    }

    /**
     * Set the font display strategy.
     */
    public function display(string $display): static
    {
        $this->display = $display;

        return $this;
    }

    /**
     * Set the font subsets to load.
     */
    public function subsets(array $subsets): static
    {
        $this->subsets = $subsets;

        return $this;
    }

    /**
     * Get the font family name for CSS.
     */
    public function getFamily(): string
    {
        return $this->family;
    }

    /**
     * Get the URL to load the font from.
     * Uses fonts.bunny.net (GDPR-compliant alternative to Google Fonts).
     */
    public function getUrl(): string
    {
        // Format: https://fonts.bunny.net/css?family=Inter:400,500,600,700&display=swap
        $familySlug = str_replace(' ', '+', $this->family);
        $weights = implode(',', $this->weights);

        $url = "https://fonts.bunny.net/css?family={$familySlug}:{$weights}";

        if ($this->display) {
            $url .= "&display={$this->display}";
        }

        return $url;
    }

    /**
     * Get the URL for Google Fonts directly.
     * Use this if you prefer Google Fonts over Bunny.net.
     */
    public function getGoogleUrl(): string
    {
        // Format: https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap
        $familySlug = str_replace(' ', '+', $this->family);
        $weights = implode(';', $this->weights);

        $url = "https://fonts.googleapis.com/css2?family={$familySlug}:wght@{$weights}";

        if ($this->display) {
            $url .= "&display={$this->display}";
        }

        return $url;
    }

    /**
     * Get the font weights to load.
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    /**
     * Get the font subsets.
     */
    public function getSubsets(): array
    {
        return $this->subsets;
    }

    /**
     * Get the font display strategy.
     */
    public function getDisplay(): ?string
    {
        return $this->display;
    }

    /**
     * Convert the font provider to an array.
     */
    public function toArray(): array
    {
        return [
            'provider' => 'google',
            'family' => $this->family,
            'url' => $this->getUrl(),
            'weights' => $this->weights,
            'subsets' => $this->subsets,
            'display' => $this->display,
        ];
    }
}
