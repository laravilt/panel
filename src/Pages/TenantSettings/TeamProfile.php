<?php

namespace Laravilt\Panel\Pages\TenantSettings;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravilt\Panel\Clusters\TenantSettings;
use Laravilt\Panel\Enums\PageLayout;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Pages\Page;

class TeamProfile extends Page
{
    protected static ?string $title = null;

    protected static ?string $cluster = TenantSettings::class;

    protected static ?string $slug = 'profile';

    protected static ?string $navigationIcon = 'building-2';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 1;

    protected ?string $component = 'Tenant/Settings/TeamProfile';

    public static function getTitle(): string
    {
        return __('panel::panel.tenancy.settings.team_name_section');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel::panel.tenancy.settings.team_name_section');
    }

    public function getHeading(): string
    {
        return __('panel::panel.tenancy.settings.team_name_section');
    }

    public function getSubheading(): ?string
    {
        return __('panel::panel.tenancy.settings.team_name_description');
    }

    public function getLayout(): string
    {
        return PageLayout::Settings->value;
    }

    public static function canAccess(): bool
    {
        return TenantSettings::canAccess();
    }

    protected function getSchema(): array
    {
        return [];
    }

    protected function getActions(): array
    {
        return [];
    }

    protected function getInertiaProps(): array
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();
        $user = request()->user();
        $slugAttribute = $panel->getTenantSlugAttribute();

        $isOwner = $this->isTeamOwner($tenant, $user);

        return [
            'team' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
                'slug' => $tenant->{$slugAttribute},
                'owner_id' => $tenant->owner_id ?? null,
            ],
            'isOwner' => $isOwner,
            'permissions' => [
                'canUpdateTeam' => $isOwner,
            ],
        ];
    }

    /**
     * Update the team name.
     */
    public function update(Request $request)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        if (! $this->isTeamOwner($tenant, $request->user())) {
            return back()->withErrors(['team' => 'You are not authorized to update this team.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant->name = $validated['name'];
        $tenant->save();

        return back()->with('success', __('panel::panel.tenancy.settings.team_updated'));
    }

    protected function isTeamOwner($tenant, $user): bool
    {
        if (! $user) {
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
