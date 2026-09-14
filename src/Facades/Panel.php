<?php

namespace Laravilt\Panel\Facades;

use Illuminate\Support\Facades\Facade;
use Laravilt\Panel\PanelRegistry;

/**
 * @method static \Laravilt\Panel\Panel|null get(string $id)
 * @method static \Illuminate\Support\Collection all()
 * @method static \Laravilt\Panel\Panel|null getDefault()
 * @method static \Laravilt\Panel\Panel|null getCurrent()
 * @method static bool has(string $id)
 * @method static void setCurrent(string $id)
 *
 * @see PanelRegistry
 */
class Panel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PanelRegistry::class;
    }
}
