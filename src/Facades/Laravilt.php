<?php

namespace Laravilt\Panel\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Laravilt\Panel\TenantManager;

/**
 * @method static void setTenant(?Model $tenant)
 * @method static Model|null getTenant()
 * @method static mixed getTenantId()
 * @method static bool hasTenant()
 * @method static Collection getTenants()
 * @method static Model|null getDefaultTenant()
 * @method static bool canAccessTenant(Model $tenant)
 * @method static string|null getTenantOwnershipColumn()
 * @method static string|null getTenantModel()
 * @method static bool isTenancyEnabled()
 * @method static string|null getTenantUrlSegment()
 *
 * @see TenantManager
 */
class Laravilt extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantManager::class;
    }
}
