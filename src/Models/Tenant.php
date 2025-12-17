<?php

namespace Laravilt\Panel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravilt\Panel\Contracts\HasTenantAvatar;
use Laravilt\Panel\Contracts\HasTenantName;

class Tenant extends Model implements HasTenantAvatar, HasTenantName
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenants';

    /**
     * Get the database connection for the model.
     * Tenants are ALWAYS stored in the central database, not in tenant databases.
     */
    public function getConnectionName()
    {
        return config('laravilt-tenancy.central.connection', config('database.default'));
    }

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'name',
        'slug',
        'email',
        'avatar',
        'description',
        'owner_id',
        'database',
        'data',
        'settings',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'data' => 'array',
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->id)) {
                $tenant->id = (string) Str::ulid();
            }

            if (empty($tenant->slug) && ! empty($tenant->name)) {
                $tenant->slug = Str::slug($tenant->name);
            }

            if (empty($tenant->database)) {
                $prefix = config('laravilt-tenancy.tenant.database_prefix', 'tenant_');
                $suffix = config('laravilt-tenancy.tenant.database_suffix', '');
                $tenant->database = $prefix.$tenant->slug.$suffix;
            }
        });
    }

    /**
     * Get the tenant's database name.
     */
    public function getDatabaseName(): string
    {
        return $this->database ?? $this->slug;
    }

    /**
     * Get the domains for this tenant.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    /**
     * Get the primary domain for this tenant.
     */
    public function primaryDomain(): ?Domain
    {
        return $this->domains()->where('is_primary', true)->first();
    }

    /**
     * Get the users associated with this tenant.
     */
    public function users(): BelongsToMany
    {
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        return $this->belongsToMany($userModel, 'tenant_users', 'tenant_id', 'user_id')
            ->withPivot(['role', 'permissions', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the owner of this tenant.
     */
    public function owner(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'owner_id');
    }

    /**
     * Get the owner user via pivot table (legacy support).
     */
    public function ownerFromPivot()
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }

    /**
     * Get the admins of this tenant.
     */
    public function admins()
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    /**
     * Get the members of this tenant.
     */
    public function members()
    {
        return $this->users()->wherePivot('role', 'member');
    }

    /**
     * Check if a user is the owner of this tenant.
     */
    public function isOwner($user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    /**
     * Check if a user is an admin of this tenant.
     */
    public function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->users()
            ->where('user_id', $user->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    /**
     * Check if a user is a member of this tenant.
     */
    public function isMember($user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Add a user to this tenant.
     */
    public function addUser($user, string $role = 'member', array $permissions = []): void
    {
        $this->users()->attach($user->id, [
            'role' => $role,
            'permissions' => json_encode($permissions),
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    /**
     * Remove a user from this tenant.
     */
    public function removeUser($user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a setting value.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    /**
     * Get a data value.
     */
    public function getData(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Set a data value.
     */
    public function setData(string $key, $value): void
    {
        $data = $this->data ?? [];
        data_set($data, $key, $value);
        $this->data = $data;
    }

    /**
     * Check if the tenant is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the trial has ended.
     */
    public function trialEnded(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    /**
     * Get the display name for the tenant.
     */
    public function getTenantName(): string
    {
        return $this->name ?? $this->slug ?? 'Unnamed Tenant';
    }

    /**
     * Get the tenant's avatar URL.
     */
    public function getTenantAvatarUrl(): ?string
    {
        if ($this->avatar) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar);
        }

        return null;
    }

    /**
     * Get route key for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
