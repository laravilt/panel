<?php

namespace Laravilt\Panel\Pages\Tenancy;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravilt\Actions\Action;
use Laravilt\Forms\Components\Textarea;
use Laravilt\Forms\Components\TextInput;
use Laravilt\Panel\Events\TenantCreated;
use Laravilt\Panel\Events\TenantDatabaseCreated;
use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Models\Domain;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Pages\Page;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class RegisterTenantMultiDb extends Page
{
    protected static ?string $title = null;

    protected static string $view = 'Tenant/RegisterMultiDb';

    protected static ?string $slug = 'tenant/register';

    protected static ?string $navigationIcon = 'building-2';

    protected static bool $shouldRegisterNavigation = false;

    public static function getTitle(): string
    {
        return __('panel::panel.tenancy.register_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel::panel.tenancy.register_title');
    }

    protected function getSchema(): array
    {
        $panel = Panel::getCurrent();
        $baseDomain = $panel?->getTenantDomain() ?? config('laravilt-tenancy.subdomain.domain', 'localhost');

        return [
            TextInput::make('name')
                ->label(__('panel::panel.tenancy.team_name'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('panel::panel.tenancy.team_name_placeholder')),

            TextInput::make('subdomain')
                ->label(__('panel::panel.tenancy.subdomain'))
                ->required()
                ->maxLength(63)
                ->placeholder(__('panel::panel.tenancy.subdomain_placeholder'))
                ->helperText(__('panel::panel.tenancy.subdomain_helper', ['domain' => $baseDomain]))
                ->prefix('')
                ->suffix('.'.$baseDomain)
                ->rules([
                    'required',
                    'string',
                    'max:63',
                    'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/',
                    function ($attribute, $value, $fail) {
                        $panel = Panel::getCurrent();
                        if ($panel?->isReservedSubdomain($value)) {
                            $fail(__('panel::panel.tenancy.subdomain_reserved'));
                        }

                        $tenantModel = config('laravilt-tenancy.models.tenant', Tenant::class);
                        if ($tenantModel::where('slug', $value)->exists()) {
                            $fail(__('panel::panel.tenancy.subdomain_taken'));
                        }

                        $domainModel = config('laravilt-tenancy.models.domain', Domain::class);
                        $baseDomain = $panel?->getTenantDomain() ?? config('laravilt-tenancy.subdomain.domain');
                        $fullDomain = "{$value}.{$baseDomain}";

                        if ($domainModel::where('domain', $fullDomain)->exists()) {
                            $fail(__('panel::panel.tenancy.subdomain_taken'));
                        }
                    },
                ]),

            Textarea::make('description')
                ->label(__('panel::panel.tenancy.team_description'))
                ->maxLength(1000)
                ->placeholder(__('panel::panel.tenancy.team_description_placeholder'))
                ->rows(3),
        ];
    }

    protected function getActions(): array
    {
        return [
            Action::make('register-tenant')
                ->label(__('panel::panel.tenancy.create_team'))
                ->action(function (array $data, Request $request) {
                    return $this->registerTenant($data, $request);
                }),
        ];
    }

    /**
     * Handle the tenant registration.
     */
    public function registerTenant(array $data, Request $request)
    {
        $panel = Panel::getCurrent();
        $user = $request->user();

        $tenantModel = config('laravilt-tenancy.models.tenant', Tenant::class);
        $domainModel = config('laravilt-tenancy.models.domain', Domain::class);
        $baseDomain = $panel->getTenantDomain() ?? config('laravilt-tenancy.subdomain.domain');

        DB::beginTransaction();

        try {
            // Create the tenant
            $tenant = $tenantModel::create([
                'name' => $data['name'],
                'slug' => $data['subdomain'],
                'description' => $data['description'] ?? null,
            ]);

            // Create the primary domain
            $fullDomain = "{$data['subdomain']}.{$baseDomain}";
            $domainModel::create([
                'domain' => $fullDomain,
                'tenant_id' => $tenant->id,
                'is_primary' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ]);

            // Associate user with tenant as owner
            $tenant->addUser($user, 'owner');

            // Fire TenantCreated event (will trigger database creation in multi-db mode)
            event(new TenantCreated($tenant));

            // If auto-provisioning is enabled and not queued, wait for database to be ready
            if (config('laravilt-tenancy.provisioning.auto_create_database', true)
                && ! config('laravilt-tenancy.provisioning.queue', false)) {
                // Get the manager and create database
                $manager = app(MultiDatabaseManager::class);

                if ($manager->createDatabase($tenant)) {
                    event(new TenantDatabaseCreated($tenant));
                }
            }

            DB::commit();

            // Redirect to the tenant's subdomain
            $scheme = $request->secure() ? 'https://' : 'http://';
            $redirectUrl = "{$scheme}{$fullDomain}/{$panel->getPath()}";

            notify(__('panel::panel.tenancy.team_created'));

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle form submission.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/',
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->registerTenant($validated, $request);
    }

    protected function getInertiaProps(): array
    {
        $panel = Panel::getCurrent();
        $baseDomain = $panel?->getTenantDomain() ?? config('laravilt-tenancy.subdomain.domain', 'localhost');

        return [
            'baseDomain' => $baseDomain,
            'reservedSubdomains' => config('laravilt-tenancy.subdomain.reserved', []),
            'isMultiDatabase' => true,
        ];
    }
}
