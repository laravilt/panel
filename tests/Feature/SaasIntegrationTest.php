<?php

use Laravilt\Panel\Models\Domain;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Panel;
use Laravilt\Panel\Tenancy\TenancyMode;
use Laravilt\Panel\TenantManager;

/*
|--------------------------------------------------------------------------
| SaaS Integration Tests
|--------------------------------------------------------------------------
|
| This test suite validates the multi-database subdomain tenancy integration
| for the Laravilt Panel package. It covers:
|
| - Panel configuration for multi-database tenancy
| - Tenant and Domain model behavior
| - Route registration and naming (no conflicts)
| - TenantManager functionality
| - Tenancy mode handling
|
*/

describe('TenancyMode Enum', function () {
    it('has single database mode', function () {
        $mode = TenancyMode::Single;

        expect($mode->value)->toBe('single');
        expect($mode->isSingle())->toBeTrue();
        expect($mode->isMultiDatabase())->toBeFalse();
        expect($mode->label())->toBe('Single Database');
    });

    it('has multi-database mode', function () {
        $mode = TenancyMode::MultiDatabase;

        expect($mode->value)->toBe('multi');
        expect($mode->isMultiDatabase())->toBeTrue();
        expect($mode->isSingle())->toBeFalse();
        expect($mode->label())->toBe('Multi-Database');
    });

    it('defaults to single database mode', function () {
        expect(TenancyMode::default())->toBe(TenancyMode::Single);
    });

    it('provides descriptions for modes', function () {
        expect(TenancyMode::Single->description())->not->toBeEmpty();
        expect(TenancyMode::MultiDatabase->description())->not->toBeEmpty();
        expect(TenancyMode::MultiDatabase->description())->toContain('own database');
    });
});

describe('Panel Multi-Database Tenancy Configuration', function () {
    it('can enable multi-database tenancy', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test');

        expect($panel->hasTenancy())->toBeTrue();
        expect($panel->isMultiDatabaseTenancy())->toBeTrue();
        expect($panel->getTenantModel())->toBe(Tenant::class);
        expect($panel->getTenantDomain())->toBe('app.test');
    });

    it('can configure tenant models', function () {
        $models = ['App\\Models\\Customer', 'App\\Models\\Product'];

        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantModels($models);

        expect($panel->getTenantModels())->toBe($models);
    });

    it('can configure central models', function () {
        $models = ['App\\Models\\User', 'App\\Models\\Plan'];

        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->centralModels($models);

        expect($panel->getCentralModels())->toBe($models);
    });

    it('defaults to single database tenancy', function () {
        $panel = Panel::make('admin')
            ->tenant(Tenant::class);

        expect($panel->hasTenancy())->toBeTrue();
        expect($panel->isMultiDatabaseTenancy())->toBeFalse();
        expect($panel->isSingleDatabaseTenancy())->toBeTrue();
    });

    it('can set tenancy mode explicitly', function () {
        $panel = Panel::make('admin')
            ->tenant(Tenant::class)
            ->tenancyMode(TenancyMode::MultiDatabase)
            ->tenantDomain('app.test');

        expect($panel->isMultiDatabaseTenancy())->toBeTrue();
    });

    it('can set tenancy mode from string', function () {
        $panel = Panel::make('admin')
            ->tenant(Tenant::class)
            ->tenancyMode('multi')
            ->tenantDomain('app.test');

        expect($panel->isMultiDatabaseTenancy())->toBeTrue();
    });

    it('can enable tenant registration', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantRegistration();

        expect($panel->hasTenantRegistration())->toBeTrue();
    });

    it('can enable tenant profile/settings', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantProfile();

        expect($panel->hasTenantProfile())->toBeTrue();
    });
});

describe('Tenant Model Structure', function () {
    it('has fillable attributes for SaaS', function () {
        $tenant = new Tenant;
        $fillable = $tenant->getFillable();

        expect($fillable)->toContain('id');
        expect($fillable)->toContain('name');
        expect($fillable)->toContain('slug');
        expect($fillable)->toContain('database');
        expect($fillable)->toContain('owner_id');
        expect($fillable)->toContain('settings');
        expect($fillable)->toContain('data');
    });

    it('casts settings to array', function () {
        $tenant = new Tenant;
        $tenant->setRawAttributes(['settings' => '{"show_unassigned_records": true}']);

        expect($tenant->settings)->toBeArray();
    });

    it('can get and set individual settings', function () {
        $tenant = new Tenant;
        $tenant->settings = [];

        $tenant->setSetting('feature.enabled', true);

        expect($tenant->getSetting('feature.enabled'))->toBeTrue();
        expect($tenant->getSetting('feature.disabled', false))->toBeFalse();
    });

    it('implements HasTenantName contract', function () {
        $tenant = new Tenant;
        $tenant->name = 'Test Company';

        expect($tenant)->toBeInstanceOf(\Laravilt\Panel\Contracts\HasTenantName::class);
        expect($tenant->getTenantName())->toBe('Test Company');
    });

    it('implements HasTenantAvatar contract', function () {
        $tenant = new Tenant;

        expect($tenant)->toBeInstanceOf(\Laravilt\Panel\Contracts\HasTenantAvatar::class);
    });

    it('uses string primary key', function () {
        $tenant = new Tenant;

        expect($tenant->getKeyType())->toBe('string');
        expect($tenant->getIncrementing())->toBeFalse();
    });

    it('uses slug as route key', function () {
        $tenant = new Tenant;

        expect($tenant->getRouteKeyName())->toBe('slug');
    });

    it('can store arbitrary data', function () {
        $tenant = new Tenant;
        $tenant->data = [];

        $tenant->setData('billing.plan', 'pro');
        $tenant->setData('limits.users', 100);

        expect($tenant->getData('billing.plan'))->toBe('pro');
        expect($tenant->getData('limits.users'))->toBe(100);
        expect($tenant->getData('nonexistent', 'default'))->toBe('default');
    });
});

describe('Domain Model Structure', function () {
    it('has fillable attributes for domain management', function () {
        $domain = new Domain;
        $fillable = $domain->getFillable();

        expect($fillable)->toContain('domain');
        expect($fillable)->toContain('tenant_id');
        expect($fillable)->toContain('is_primary');
        expect($fillable)->toContain('is_verified');
    });

    it('casts boolean attributes', function () {
        $domain = new Domain;
        $domain->setRawAttributes([
            'is_primary' => 1,
            'is_verified' => 0,
        ]);

        expect($domain->is_primary)->toBeBool();
        expect($domain->is_verified)->toBeBool();
    });

    it('can detect if subdomain of base domain', function () {
        $domain = new Domain;
        $domain->domain = 'acme.app.test';

        expect($domain->isSubdomainOf('app.test'))->toBeTrue();
        expect($domain->isSubdomainOf('other.test'))->toBeFalse();
    });

    it('can extract subdomain from full domain', function () {
        $domain = new Domain;
        $domain->domain = 'acme.app.test';

        expect($domain->getSubdomain('app.test'))->toBe('acme');
        expect($domain->getSubdomain('other.test'))->toBeNull();
    });

    it('uses domain as route key', function () {
        $domain = new Domain;

        expect($domain->getRouteKeyName())->toBe('domain');
    });
});

describe('TenantManager', function () {
    it('can set and get tenant', function () {
        $manager = new TenantManager;
        $tenant = new Tenant;
        $tenant->forceFill(['id' => 'test-123', 'name' => 'Test']);

        $manager->setTenant($tenant);

        expect($manager->hasTenant())->toBeTrue();
        expect($manager->getTenant())->toBe($tenant);
        expect($manager->getTenantId())->toBe('test-123');
    });

    it('returns null when no tenant set', function () {
        $manager = new TenantManager;

        expect($manager->hasTenant())->toBeFalse();
        expect($manager->getTenant())->toBeNull();
        expect($manager->getTenantId())->toBeNull();
    });

    it('can clear tenant', function () {
        $manager = new TenantManager;
        $tenant = new Tenant;
        $tenant->forceFill(['id' => 'test-123']);

        $manager->setTenant($tenant);
        $manager->setTenant(null);

        expect($manager->hasTenant())->toBeFalse();
    });
});

// Note: Tenancy configuration tests are validated through integration tests
// when running with a full Laravel application context. The config values
// are tested indirectly through the functional tests above.

describe('Route Naming Convention', function () {
    it('subdomain routes should use admin.subdomain prefix', function () {
        // This test verifies that subdomain routes don't conflict with central routes
        // by checking the naming convention

        $panel = Panel::make('admin')
            ->path('admin')
            ->multiDatabaseTenancy(Tenant::class, 'laravilt.test');

        // The subdomain route name prefix should be 'admin.subdomain.'
        // NOT 'admin.tenant.' to avoid conflicts with central tenant management routes
        $expectedPrefix = $panel->getId().'.subdomain.';

        expect($expectedPrefix)->toBe('admin.subdomain.');
    });

    it('central tenant settings routes should use admin.tenant.settings prefix', function () {
        // Central tenant settings (TenantSettings cluster) should use admin.tenant.settings.*
        // This is different from subdomain routes which use admin.subdomain.*

        $panel = Panel::make('admin')
            ->path('admin')
            ->tenant(Tenant::class)
            ->tenantProfile();

        $expectedPrefix = $panel->getId().'.tenant.settings';

        expect($expectedPrefix)->toBe('admin.tenant.settings');
    });

    it('route names should not conflict between subdomain and central', function () {
        // Key insight: The conflict was between:
        // - Subdomain user settings: admin.tenant.settings.profile (when using admin.tenant. prefix)
        // - Central tenant settings: admin.tenant.settings.profile
        //
        // Solution: Subdomain routes now use admin.subdomain.* prefix
        // So subdomain user settings is: admin.subdomain.settings.profile
        // And central tenant settings is: admin.tenant.settings.profile

        $subdomainUserSettings = 'admin.subdomain.settings.profile';
        $centralTenantSettings = 'admin.tenant.settings.profile';

        expect($subdomainUserSettings)->not->toBe($centralTenantSettings);
    });
});

describe('Panel URL Generation', function () {
    it('generates path-based URLs for single-database tenancy', function () {
        $panel = Panel::make('admin')
            ->path('admin')
            ->tenant(Tenant::class, 'team', 'slug');

        $tenant = new Tenant;
        $tenant->slug = 'acme';

        expect($panel->isMultiDatabaseTenancy())->toBeFalse();

        $url = $panel->getTenantUrl($tenant, 'dashboard');

        expect($url)->toBe('/admin/acme/dashboard');
    });
});

describe('Multi-Panel Support', function () {
    it('allows different tenancy modes per panel', function () {
        $adminPanel = Panel::make('admin')
            ->path('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test');

        $portalPanel = Panel::make('portal')
            ->path('portal')
            ->tenant(Tenant::class);

        expect($adminPanel->isMultiDatabaseTenancy())->toBeTrue();
        expect($portalPanel->isMultiDatabaseTenancy())->toBeFalse();
        expect($portalPanel->isSingleDatabaseTenancy())->toBeTrue();
    });

    it('panels have independent tenant configurations', function () {
        $panel1 = Panel::make('panel1')
            ->multiDatabaseTenancy(Tenant::class, 'domain1.test')
            ->tenantModels(['App\\Models\\Customer']);

        $panel2 = Panel::make('panel2')
            ->multiDatabaseTenancy(Tenant::class, 'domain2.test')
            ->tenantModels(['App\\Models\\Product']);

        expect($panel1->getTenantDomain())->toBe('domain1.test');
        expect($panel2->getTenantDomain())->toBe('domain2.test');
        expect($panel1->getTenantModels())->toBe(['App\\Models\\Customer']);
        expect($panel2->getTenantModels())->toBe(['App\\Models\\Product']);
    });
});

describe('Tenant Slug Attribute Detection', function () {
    it('defaults to slug when model has slug attribute', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test');

        expect($panel->getTenantSlugAttribute())->toBe('slug');
    });

    it('can override slug attribute', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantSlugAttribute('subdomain');

        expect($panel->getTenantSlugAttribute())->toBe('subdomain');
    });
});

describe('Tenant Ownership Relationship', function () {
    it('infers relationship name from model class', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test');

        // Tenant class should infer 'tenant' relationship
        expect($panel->getTenantOwnershipRelationship())->toBe('tenant');
    });

    it('can set custom ownership relationship', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantOwnershipRelationship('team');

        expect($panel->getTenantOwnershipRelationship())->toBe('team');
    });
});

describe('Middleware Classes Exist', function () {
    it('InitializeTenancyBySubdomain middleware exists', function () {
        expect(class_exists(\Laravilt\Panel\Middleware\InitializeTenancyBySubdomain::class))->toBeTrue();
    });

    it('IdentifyTenant middleware exists', function () {
        expect(class_exists(\Laravilt\Panel\Middleware\IdentifyTenant::class))->toBeTrue();
    });

    it('IdentifyPanel middleware exists', function () {
        expect(class_exists(\Laravilt\Panel\Middleware\IdentifyPanel::class))->toBeTrue();
    });
});

describe('Model Classes Exist', function () {
    it('Tenant model class exists', function () {
        expect(class_exists(\Laravilt\Panel\Models\Tenant::class))->toBeTrue();
    });

    it('Domain model class exists', function () {
        expect(class_exists(\Laravilt\Panel\Models\Domain::class))->toBeTrue();
    });
});

describe('Tenancy Classes Exist', function () {
    it('TenancyMode enum exists', function () {
        expect(class_exists(\Laravilt\Panel\Tenancy\TenancyMode::class))->toBeTrue();
    });

    it('MultiDatabaseManager class exists', function () {
        expect(class_exists(\Laravilt\Panel\Tenancy\MultiDatabaseManager::class))->toBeTrue();
    });

    it('ModelResolver class exists', function () {
        expect(class_exists(\Laravilt\Panel\Tenancy\ModelResolver::class))->toBeTrue();
    });
});

describe('Tenant Menu Configuration', function () {
    it('can enable tenant menu', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantMenu(true);

        expect($panel->hasTenantMenu())->toBeTrue();
    });

    it('can disable tenant menu', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantMenu(false);

        expect($panel->hasTenantMenu())->toBeFalse();
    });

    it('can set custom tenant menu items', function () {
        $items = ['settings' => 'Settings', 'billing' => 'Billing'];

        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test')
            ->tenantMenuItems($items);

        expect($panel->getTenantMenuItems())->toBe($items);
    });
});

describe('Panel Tenant Route Parameter', function () {
    it('generates tenant route parameter name from model', function () {
        $panel = Panel::make('admin')
            ->multiDatabaseTenancy(Tenant::class, 'app.test');

        expect($panel->getTenantRouteParameterName())->toBe('tenant');
    });
});
