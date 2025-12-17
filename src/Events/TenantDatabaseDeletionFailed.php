<?php

namespace Laravilt\Panel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravilt\Panel\Models\Tenant;

class TenantDatabaseDeletionFailed
{
    use Dispatchable, SerializesModels;

    /**
     * The tenant instance.
     */
    public Tenant $tenant;

    /**
     * The exception that caused the failure.
     */
    public ?\Throwable $exception;

    /**
     * Create a new event instance.
     */
    public function __construct(Tenant $tenant, ?\Throwable $exception = null)
    {
        $this->tenant = $tenant;
        $this->exception = $exception;
    }
}
