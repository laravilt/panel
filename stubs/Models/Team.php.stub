<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravilt\Panel\Contracts\HasTenantAvatar;
use Laravilt\Panel\Contracts\HasTenantName;

class Team extends Model implements HasTenantName, HasTenantAvatar
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'avatar',
        'description',
        'owner_id',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Check if this team should show unassigned records (records with null team_id).
     * This allows team members to see and assign orphaned records to their team.
     */
    public function shouldShowUnassignedRecords(): bool
    {
        return $this->settings['show_unassigned_records'] ?? false;
    }

    /**
     * Set whether this team should show unassigned records.
     */
    public function setShowUnassignedRecords(bool $value): void
    {
        $settings = $this->settings ?? [];
        $settings['show_unassigned_records'] = $value;
        $this->settings = $settings;
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    /**
     * Get the users that belong to the team.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the owner of the team.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the display name for the tenant.
     */
    public function getTenantName(): string
    {
        return $this->name;
    }

    /**
     * Get the avatar URL for the tenant.
     */
    public function getTenantAvatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return asset('storage/'.$this->avatar);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
