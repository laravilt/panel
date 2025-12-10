<?php

namespace Laravilt\Panel\FontProviders;

interface FontProvider
{
    /**
     * Get the font family name for CSS.
     */
    public function getFamily(): string;

    /**
     * Get the URL to load the font from.
     */
    public function getUrl(): string;

    /**
     * Get the font weights to load.
     */
    public function getWeights(): array;

    /**
     * Convert the font provider to an array.
     */
    public function toArray(): array;
}
