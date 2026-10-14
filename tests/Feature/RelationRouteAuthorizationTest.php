<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravilt\Notifications\Notification;
use Laravilt\Panel\Panel;
use Laravilt\Panel\PanelServiceProvider;
use Laravilt\Panel\Resources\NestedResource;
use Laravilt\Panel\Resources\RelationManagers\RelationManager;
use Laravilt\Panel\Resources\Resource;

// The notifications package is not a dev dependency; the relation routes only use it to flash a message
if (! class_exists(Notification::class)) {
    eval('namespace Laravilt\Notifications; class Notification { public static function success(): static { return new static; } public function title($v): static { return $this; } public function body($v): static { return $this; } public function send(): static { return $this; } }');
}

class RelAuthUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];
}

class RelAuthParent extends Model
{
    protected $table = 'rel_parents';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(RelAuthChild::class, 'parent_id');
    }
}

class RelAuthChild extends Model
{
    protected $table = 'rel_children';

    protected $guarded = [];
}

/** Same tables, but no policy is registered for these models */
class RelOpenParent extends Model
{
    protected $table = 'rel_parents';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(RelOpenChild::class, 'parent_id');
    }
}

class RelOpenChild extends Model
{
    protected $table = 'rel_children';

    protected $guarded = [];
}

/** Policies whose abilities are denied by name through the static $deny list */
class RelAuthParentPolicy
{
    public static array $deny = [];

    public function viewAny($user): bool
    {
        return ! in_array('viewAny', static::$deny, true);
    }

    public function view($user, $record): bool
    {
        return ! in_array('view', static::$deny, true);
    }

    public function update($user, $record): bool
    {
        return ! in_array('update', static::$deny, true);
    }
}

class RelAuthChildPolicy
{
    public static array $deny = [];

    public function viewAny($user): bool
    {
        return ! in_array('viewAny', static::$deny, true);
    }

    public function view($user, $record): bool
    {
        return ! in_array('view', static::$deny, true);
    }

    public function create($user): bool
    {
        return ! in_array('create', static::$deny, true);
    }

    public function update($user, $record): bool
    {
        return ! in_array('update', static::$deny, true);
    }

    public function delete($user, $record): bool
    {
        return ! in_array('delete', static::$deny, true);
    }

    public function deleteAny($user): bool
    {
        return ! in_array('delete', static::$deny, true);
    }
}

class RelAuthItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
}

class RelAuthParentResource extends Resource
{
    protected static string $model = RelAuthParent::class;

    protected static ?string $slug = 'relparents';

    public static function getRelations(): array
    {
        return [RelAuthItemsRelationManager::class];
    }

    public static function getNestedResources(): array
    {
        return [RelAuthNestedResource::class];
    }
}

class RelAuthNestedResource extends NestedResource
{
    protected static string $model = RelAuthChild::class;

    protected static ?string $slug = 'nested-items';

    protected static ?string $parentResource = RelAuthParentResource::class;

    protected static string $parentRelationship = 'parent';

    protected static ?string $childRelationship = 'items';

    public static function getPages(): array
    {
        return [];
    }
}

class RelOpenParentResource extends Resource
{
    protected static string $model = RelOpenParent::class;

    protected static ?string $slug = 'openparents';

    public static function getRelations(): array
    {
        return [RelAuthItemsRelationManager::class];
    }

    public static function getNestedResources(): array
    {
        return [RelOpenNestedResource::class];
    }
}

class RelOpenNestedResource extends NestedResource
{
    protected static string $model = RelOpenChild::class;

    protected static ?string $slug = 'nested-items';

    protected static ?string $parentResource = RelOpenParentResource::class;

    protected static string $parentRelationship = 'parent';

    protected static ?string $childRelationship = 'items';

    public static function getPages(): array
    {
        return [];
    }
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
    Schema::create('rel_parents', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });
    Schema::create('rel_children', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('title')->nullable();
        $table->timestamps();
    });

    RelAuthParentPolicy::$deny = [];
    RelAuthChildPolicy::$deny = [];

    Gate::policy(RelAuthParent::class, RelAuthParentPolicy::class);
    Gate::policy(RelAuthChild::class, RelAuthChildPolicy::class);

    $provider = app()->getProvider(PanelServiceProvider::class);
    $panel = Panel::make('admin');

    Route::middleware('web')->prefix('admin')->group(function () use ($provider, $panel) {
        (function () use ($panel) {
            foreach ([
                [RelAuthParentResource::class, 'relparents', RelAuthParent::class],
                [RelOpenParentResource::class, 'openparents', RelOpenParent::class],
            ] as [$resource, $slug, $model]) {
                $this->registerReorderRoute($resource, $slug, $model, $panel);
                $this->registerRelationManagerRoutes($resource, $slug, $model, $panel);
                $this->registerNestedResourceRoutes($resource, $slug, $model, $panel);
            }
        })->call($provider);
    });

    $this->actingAs(RelAuthUser::create(['name' => 'u', 'email' => 'u@example.com', 'password' => 'x']));

    $this->parent = RelAuthParent::create(['title' => 'p']);
    $this->child = $this->parent->items()->create(['title' => 'c']);
});

describe('relation manager routes', function () {
    it('returns 403 when the parent record cannot be viewed', function () {
        RelAuthParentPolicy::$deny = ['view'];
        $base = '/admin/relparents/'.$this->parent->id.'/relations/items';

        $this->getJson($base)->assertForbidden();
        $this->postJson($base, ['title' => 'x'])->assertForbidden();
        $this->putJson($base.'/'.$this->child->id, ['title' => 'x'])->assertForbidden();
        $this->deleteJson($base.'/'.$this->child->id)->assertForbidden();
        $this->postJson($base.'/bulk-delete', ['ids' => [$this->child->id]])->assertForbidden();

        expect(RelAuthChild::count())->toBe(1)
            ->and($this->child->fresh()->title)->toBe('c');
    });

    it('returns 403 listing related records without viewAny on the related model', function () {
        RelAuthChildPolicy::$deny = ['viewAny'];

        $this->getJson('/admin/relparents/'.$this->parent->id.'/relations/items')->assertForbidden();
    });

    it('returns 403 creating a related record without create on the related model', function () {
        RelAuthChildPolicy::$deny = ['create'];

        $this->postJson('/admin/relparents/'.$this->parent->id.'/relations/items', ['title' => 'x'])->assertForbidden();

        expect(RelAuthChild::count())->toBe(1);
    });

    it('returns 403 updating a related record without update on the related model', function () {
        RelAuthChildPolicy::$deny = ['update'];

        $this->putJson('/admin/relparents/'.$this->parent->id.'/relations/items/'.$this->child->id, ['title' => 'x'])->assertForbidden();

        expect($this->child->fresh()->title)->toBe('c');
    });

    it('returns 403 deleting related records without delete on the related model', function () {
        RelAuthChildPolicy::$deny = ['delete'];
        $base = '/admin/relparents/'.$this->parent->id.'/relations/items';

        $this->deleteJson($base.'/'.$this->child->id)->assertForbidden();
        $this->postJson($base.'/bulk-delete', ['ids' => [$this->child->id]])->assertForbidden();

        expect(RelAuthChild::count())->toBe(1);
    });

    it('still allows everything when the policies allow it', function () {
        $base = '/admin/relparents/'.$this->parent->id.'/relations/items';

        $this->getJson($base)->assertOk();
        $this->postJson($base, ['title' => 'n'])->assertOk();
        $this->putJson($base.'/'.$this->child->id, ['title' => 'u'])->assertOk();
        $this->deleteJson($base.'/'.$this->child->id)->assertOk();

        expect($this->child->fresh())->toBeNull();
    });

    it('does not restrict relation routes of models without policies', function () {
        $parent = RelOpenParent::create(['title' => 'p']);
        $child = $parent->items()->create(['title' => 'c']);
        $base = '/admin/openparents/'.$parent->id.'/relations/items';

        $this->getJson($base)->assertOk();
        $this->postJson($base, ['title' => 'n'])->assertOk();
        $this->putJson($base.'/'.$child->id, ['title' => 'u'])->assertOk();
        $this->deleteJson($base.'/'.$child->id)->assertOk();
    });
});

describe('nested resource routes', function () {
    it('returns 403 when the parent record cannot be viewed', function () {
        RelAuthParentPolicy::$deny = ['view'];
        $base = '/admin/relparents/'.$this->parent->id.'/nested-items';

        $this->getJson($base.'/data')->assertForbidden();
        $this->postJson($base, ['title' => 'x'])->assertForbidden();
        $this->getJson($base.'/'.$this->child->id)->assertForbidden();
        $this->putJson($base.'/'.$this->child->id, ['title' => 'x'])->assertForbidden();
        $this->deleteJson($base.'/'.$this->child->id)->assertForbidden();
        $this->postJson($base.'/bulk-delete', ['ids' => [$this->child->id]])->assertForbidden();

        expect(RelAuthChild::count())->toBe(1)
            ->and($this->child->fresh()->title)->toBe('c');
    });

    it('returns 403 for each ability the nested model policy denies', function () {
        $base = '/admin/relparents/'.$this->parent->id.'/nested-items';

        RelAuthChildPolicy::$deny = ['viewAny'];
        $this->getJson($base.'/data')->assertForbidden();

        RelAuthChildPolicy::$deny = ['create'];
        $this->postJson($base, ['title' => 'x'])->assertForbidden();

        RelAuthChildPolicy::$deny = ['view'];
        $this->getJson($base.'/'.$this->child->id)->assertForbidden();

        RelAuthChildPolicy::$deny = ['update'];
        $this->putJson($base.'/'.$this->child->id, ['title' => 'x'])->assertForbidden();

        RelAuthChildPolicy::$deny = ['delete'];
        $this->deleteJson($base.'/'.$this->child->id)->assertForbidden();
        $this->postJson($base.'/bulk-delete', ['ids' => [$this->child->id]])->assertForbidden();

        expect(RelAuthChild::count())->toBe(1)
            ->and($this->child->fresh()->title)->toBe('c');
    });

    it('does not reach children of another parent through this parent', function () {
        $other = RelAuthParent::create(['title' => 'other']);
        $foreign = $other->items()->create(['title' => 'foreign']);
        $base = '/admin/relparents/'.$this->parent->id.'/nested-items';

        $this->getJson($base.'/'.$foreign->id)->assertNotFound();
        $this->putJson($base.'/'.$foreign->id, ['title' => 'x'])->assertNotFound();
        $this->deleteJson($base.'/'.$foreign->id)->assertNotFound();
        $this->postJson($base.'/bulk-delete', ['ids' => [$foreign->id]])->assertOk();

        expect($foreign->fresh()->title)->toBe('foreign');
    });

    it('does not restrict nested routes of models without policies', function () {
        $parent = RelOpenParent::create(['title' => 'p']);
        $child = $parent->items()->create(['title' => 'c']);
        $base = '/admin/openparents/'.$parent->id.'/nested-items';

        $this->postJson($base, ['title' => 'n'])->assertStatus(201);
        $this->getJson($base.'/'.$child->id)->assertOk();
        $this->putJson($base.'/'.$child->id, ['title' => 'u'])->assertOk();
        $this->deleteJson($base.'/'.$child->id)->assertOk();
    });
});

describe('reorder route', function () {
    it('returns 403 when the policy denies update', function () {
        RelAuthParentPolicy::$deny = ['update'];

        $this->postJson('/admin/relparents/reorder', [
            'items' => [['id' => $this->parent->id, 'order' => 5]],
            'column' => 'sort_order',
        ])->assertForbidden();

        expect($this->parent->fresh()->sort_order)->toBe(0);
    });

    it('reorders when the policy allows it', function () {
        $this->postJson('/admin/relparents/reorder', [
            'items' => [['id' => $this->parent->id, 'order' => 5]],
            'column' => 'sort_order',
        ])->assertOk();

        expect($this->parent->fresh()->sort_order)->toBe(5);
    });

    it('reorders models without policies', function () {
        $parent = RelOpenParent::create(['title' => 'p']);

        $this->postJson('/admin/openparents/reorder', [
            'items' => [['id' => $parent->id, 'order' => 7]],
            'column' => 'sort_order',
        ])->assertOk();

        expect($parent->fresh()->sort_order)->toBe(7);
    });
});
