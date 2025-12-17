<?php

namespace Laravilt\Panel\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravilt\Panel\Events\TenantSeeded;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class SeedTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The tenant instance.
     */
    public Tenant $tenant;

    /**
     * The seeder class to run.
     */
    public ?string $seeder;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant, ?string $seeder = null)
    {
        $this->tenant = $tenant;
        $this->seeder = $seeder;

        // Use configured queue
        $this->onQueue(config('laravilt-tenancy.provisioning.queue_name', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(MultiDatabaseManager $manager): void
    {
        $manager->seedTenant($this->tenant, $this->seeder);

        event(new TenantSeeded($this->tenant));
    }
}
