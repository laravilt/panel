<?php

namespace Laravilt\Panel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravilt\Panel\Models\Tenant;

class TenantDatabaseDeleted
{
    use Dispatchable, SerializesModels;

    /**
     * The tenant instance.
     */
    public Tenant $tenant;

    /**
     * Create a new event instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }
}
