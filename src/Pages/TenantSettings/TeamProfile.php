<?php

namespace Laravilt\Panel\Pages\TenantSettings;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravilt\Actions\Action;
use Laravilt\Forms\Components\TextInput;
use Laravilt\Forms\Components\Toggle;
use Laravilt\Panel\Clusters\TenantSettings;
use Laravilt\Panel\Enums\PageLayout;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Pages\Page;
use Laravilt\Schemas\Components\Section;

class TeamProfile extends Page
{
    protected static ?string $title = null;

    protected static ?string $cluster = TenantSettings::class;

    protected static string $view = 'Tenant/TeamProfile';

    protected static ?string $slug = 'profile';

    protected static ?string $navigationIcon = 'building-2';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 1;

    public static function getTitle(): string
    {
        return __('panel::panel.tenancy.settings.team_profile_section');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel::panel.tenancy.settings.team_profile_section');
    }

    public function getHeading(): string
    {
        return __('panel::panel.tenancy.settings.team_profile_section');
    }

    public function getSubheading(): ?string
    {
        return __('panel::panel.tenancy.settings.team_profile_description');
    }

    public function getLayout(): string
    {
        return PageLayout::Settings->value;
    }

    public static function canAccess(): bool
    {
        // Delegate to cluster - tenant existence is checked by middleware
        return TenantSettings::canAccess();
    }

    protected function getSchema(): array
    {
        $tenant = Laravilt::getTenant();
        $isOwner = $this->isTeamOwner($tenant, request()->user());

        $textInput = TextInput::make('name')
            ->label(__('panel::panel.tenancy.team_name'))
            ->default($tenant?->name ?? '')
            ->required()
            ->maxLength(255)
            ->disabled(! $isOwner);

        if (! $isOwner) {
            $textInput->helperText(__('panel::panel.tenancy.settings.only_owner_can_edit'));
        }

        $schema = [$textInput];

        // Add settings section for team owners
        if ($isOwner && $this->tenantHasAttribute($tenant, 'settings')) {
            $showUnassigned = $tenant->settings['show_unassigned_records'] ?? false;

            $schema[] = Section::make(__('panel::panel.tenancy.settings.team_settings'))
                ->description(__('panel::panel.tenancy.settings.team_settings_description'))
                ->schema([
                    Toggle::make('show_unassigned_records')
                        ->label(__('panel::panel.tenancy.settings.show_unassigned_records'))
                        ->helperText(__('panel::panel.tenancy.settings.show_unassigned_records_description'))
                        ->default($showUnassigned),
                ]);
        }

        return $schema;
    }

    protected function getActions(): array
    {
        $tenant = Laravilt::getTenant();
        $isOwner = $this->isTeamOwner($tenant, request()->user());

        if (! $isOwner) {
            return [];
        }

        return [
            Action::make('update-team-name')
                ->label(__('panel::panel.common.save'))
                ->action(function (array $data) {
                    return $this->updateTeamName($data);
                }),
        ];
    }

    /**
     * Update the team profile via action.
     */
    public function updateTeamProfile(array $data, ?Request $request = null): mixed
    {
        $tenant = Laravilt::getTenant();
        $request = $request ?? request();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        if (! $this->isTeamOwner($tenant, $request->user())) {
            return back()->withErrors(['team' => 'You are not authorized to update this team.']);
        }

        // Update name
        $tenant->name = $data['name'];

        // Update description if the tenant model has this field
        if (array_key_exists('description', $data) && $this->tenantHasAttribute($tenant, 'description')) {
            $tenant->description = $data['description'];
        }

        // Update settings if the tenant model has this field
        if ($this->tenantHasAttribute($tenant, 'settings')) {
            $settings = $tenant->settings ?? [];

            // Update show_unassigned_records setting
            // Note: FormData may not include false boolean values, so we check the request directly
            // and treat missing/empty values as false
            $showUnassigned = $request->input('show_unassigned_records');
            $settings['show_unassigned_records'] = filter_var($showUnassigned, FILTER_VALIDATE_BOOLEAN);

            $tenant->settings = $settings;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->uploadAvatar($request->file('avatar'), $tenant);
            if ($avatarPath && $this->tenantHasAttribute($tenant, 'avatar')) {
                // Delete old avatar if exists
                if ($tenant->avatar) {
                    Storage::disk('public')->delete($tenant->avatar);
                }
                $tenant->avatar = $avatarPath;
            }
        } elseif (isset($data['remove_avatar']) && $data['remove_avatar'] && $this->tenantHasAttribute($tenant, 'avatar')) {
            // Remove avatar
            if ($tenant->avatar) {
                Storage::disk('public')->delete($tenant->avatar);
            }
            $tenant->avatar = null;
        }

        $tenant->save();

        // Refresh the tenant instance so Laravilt::getTenant() returns fresh data
        $tenant->refresh();
        Laravilt::setTenant($tenant);

        // Clear the tenant cache so the next request gets fresh data
        $this->clearTenantCache($tenant);

        notify(__('panel::panel.tenancy.settings.team_updated'));

        return back();
    }

    /**
     * Upload avatar file.
     */
    protected function uploadAvatar($file, $tenant): ?string
    {
        if (! $file) {
            return null;
        }

        $filename = 'teams/'.$tenant->getKey().'/avatar-'.time().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('', $filename, 'public');
    }

    /**
     * Check if tenant model has a specific attribute.
     */
    protected function tenantHasAttribute($tenant, string $attribute): bool
    {
        return in_array($attribute, $tenant->getFillable()) ||
               array_key_exists($attribute, $tenant->getAttributes()) ||
               $tenant->getConnection()->getSchemaBuilder()->hasColumn($tenant->getTable(), $attribute);
    }

    /**
     * Clear the tenant cache after updating.
     */
    protected function clearTenantCache($tenant): void
    {
        $panel = Panel::getCurrent();
        $cachePrefix = config('laravilt-tenancy.cache.prefix', 'laravilt_tenant_');
        $baseDomain = $panel?->getTenantDomain();
        $slugAttribute = $panel?->getTenantSlugAttribute() ?? 'slug';

        // Clear cache by domain
        if ($baseDomain) {
            $fullDomain = "{$tenant->{$slugAttribute}}.{$baseDomain}";
            \Illuminate\Support\Facades\Cache::forget("{$cachePrefix}domain_{$fullDomain}");
        }

        // Also clear by slug just in case
        \Illuminate\Support\Facades\Cache::forget("{$cachePrefix}slug_{$tenant->{$slugAttribute}}");
        \Illuminate\Support\Facades\Cache::forget("{$cachePrefix}id_{$tenant->getKey()}");
    }

    /**
     * Handle POST request to update team profile.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'show_unassigned_records' => ['nullable'],
        ]);

        return $this->updateTeamProfile($validated, $request);
    }

    /**
     * Delete the team.
     */
    public function deleteTeam(): mixed
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        if (! $this->isTeamOwner($tenant, request()->user())) {
            return back()->withErrors(['team' => 'You are not authorized to delete this team.']);
        }

        // Detach all members first
        $membersRelationship = Str::plural('user');

        if (method_exists($tenant, $membersRelationship)) {
            $tenant->{$membersRelationship}()->detach();
        }

        // Delete the team
        $tenant->delete();

        // Clear tenant from session
        session()->forget('laravilt.tenant_id');

        notify(__('panel::panel.tenancy.settings.team_deleted'));

        return redirect('/'.$panel->getPath());
    }

    /**
     * Handle DELETE request to delete team.
     */
    public function destroy(Request $request)
    {
        return $this->deleteTeam();
    }

    protected function getInertiaProps(): array
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();
        $user = request()->user();
        $slugAttribute = $panel->getTenantSlugAttribute();

        $isOwner = $this->isTeamOwner($tenant, $user);

        // Get avatar URL
        $avatarUrl = null;
        if ($this->tenantHasAttribute($tenant, 'avatar') && $tenant->avatar) {
            $avatarUrl = Storage::disk('public')->url($tenant->avatar);
        } elseif (method_exists($tenant, 'getTenantAvatarUrl')) {
            $avatarUrl = $tenant->getTenantAvatarUrl();
        }

        // Get team settings
        $settings = [];
        if ($this->tenantHasAttribute($tenant, 'settings')) {
            $settings = $tenant->settings ?? [];
        }

        return [
            'team' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
                'slug' => $tenant->{$slugAttribute},
                'description' => $this->tenantHasAttribute($tenant, 'description') ? $tenant->description : null,
                'avatar' => $avatarUrl,
                'owner_id' => $tenant->owner_id ?? null,
                'settings' => $settings,
            ],
            'isOwner' => $isOwner,
            'hasSettings' => $this->tenantHasAttribute($tenant, 'settings'),
            'permissions' => [
                'canUpdateTeam' => $isOwner,
                'canDeleteTeam' => $isOwner,
            ],
            'routes' => [
                'deleteTeam' => '/'.$panel->getPath().'/tenant-settings/profile',
            ],
        ];
    }

    protected function isTeamOwner($tenant, $user): bool
    {
        if (! $user || ! $tenant) {
            return false;
        }

        if (isset($tenant->owner_id)) {
            return (int) $tenant->owner_id === (int) $user->id;
        }

        $panel = Panel::getCurrent();
        $ownershipRelationship = $panel->getTenantOwnershipRelationship();
        $pluralRelationship = Str::plural($ownershipRelationship);

        if (method_exists($user, $pluralRelationship)) {
            $membership = $user->{$pluralRelationship}()
                ->where($tenant->getTable().'.id', $tenant->getKey())
                ->first();

            return $membership && ($membership->pivot->role ?? '') === 'owner';
        }

        return false;
    }
}
