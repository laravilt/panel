<?php

namespace Laravilt\Panel\Pages\TenantSettings;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravilt\Actions\Action;
use Laravilt\Forms\Components\Checkbox;
use Laravilt\Forms\Components\Select;
use Laravilt\Forms\Components\TextInput;
use Laravilt\Panel\Clusters\TenantSettings;
use Laravilt\Panel\Enums\PageLayout;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Notifications\TeamInvitation;
use Laravilt\Panel\Pages\Page;

class TeamMembers extends Page
{
    protected static ?string $title = null;

    protected static ?string $cluster = TenantSettings::class;

    protected static string $view = 'Tenant/TeamMembers';

    protected static ?string $slug = 'members';

    protected static ?string $navigationIcon = 'users';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 2;

    public static function getTitle(): string
    {
        return __('panel::panel.tenancy.settings.team_members_section');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel::panel.tenancy.settings.team_members_section');
    }

    public function getHeading(): string
    {
        return __('panel::panel.tenancy.settings.team_members_section');
    }

    public function getSubheading(): ?string
    {
        return __('panel::panel.tenancy.settings.team_members_description');
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
        // The members list is passed via getInertiaProps()
        // The schema is for the "add member" form inside the modal action
        return [];
    }

    protected function getActions(): array
    {
        $tenant = Laravilt::getTenant();
        $isOwner = $this->isTeamOwner($tenant, request()->user());

        if (! $isOwner) {
            return [];
        }

        return [
            Action::make('invite-member')
                ->label(__('panel::panel.tenancy.settings.invite_member'))
                ->icon('user-plus')
                ->modalHeading(__('panel::panel.tenancy.settings.invite_member'))
                ->modalDescription(__('panel::panel.tenancy.settings.invite_member_description'))
                ->modalSubmitActionLabel(__('panel::panel.tenancy.settings.invite_member'))
                ->modalFormSchema([
                    TextInput::make('email')
                        ->label(__('panel::panel.tenancy.settings.email'))
                        ->email()
                        ->required()
                        ->placeholder(__('panel::panel.tenancy.settings.email_placeholder')),

                    Select::make('role')
                        ->label(__('panel::panel.tenancy.settings.role'))
                        ->options([
                            'admin' => __('panel::panel.tenancy.roles.admin'),
                            'editor' => __('panel::panel.tenancy.roles.editor'),
                            'member' => __('panel::panel.tenancy.roles.member'),
                        ])
                        ->default('member')
                        ->required(),

                    Checkbox::make('send_email')
                        ->label(__('panel::panel.tenancy.settings.send_email_notification'))
                        ->default(true),

                    Checkbox::make('send_database')
                        ->label(__('panel::panel.tenancy.settings.send_notification_center'))
                        ->default(true),
                ])
                ->action(function (array $data) {
                    return $this->inviteTeamMember($data);
                }),
        ];
    }

    /**
     * Invite a new team member.
     */
    public function inviteTeamMember(array $data): mixed
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();
        $inviter = request()->user();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        if (! $this->isTeamOwner($tenant, $inviter)) {
            return back()->withErrors(['team' => 'You are not authorized to invite members.']);
        }

        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        $invitedUser = $userModel::where('email', $data['email'])->first();

        if (! $invitedUser) {
            return back()->withErrors(['email' => __('panel::panel.tenancy.settings.user_not_found')]);
        }

        $ownershipRelationship = $panel->getTenantOwnershipRelationship();
        $pluralRelationship = Str::plural($ownershipRelationship);

        if (method_exists($invitedUser, $pluralRelationship)) {
            $existingMembership = $invitedUser->{$pluralRelationship}()
                ->where($tenant->getTable().'.id', $tenant->getKey())
                ->exists();

            if ($existingMembership) {
                return back()->withErrors(['email' => __('panel::panel.tenancy.settings.already_member')]);
            }

            $invitedUser->{$pluralRelationship}()->attach($tenant->getKey(), ['role' => $data['role']]);
        }

        // Send notification to the invited user
        $sendEmail = $data['send_email'] ?? true;
        $sendDatabase = $data['send_database'] ?? true;

        if ($sendEmail || $sendDatabase) {
            $invitedUser->notify(new TeamInvitation(
                team: $tenant,
                inviter: $inviter,
                role: $data['role'],
                panelPath: $panel->getPath(),
                sendEmail: $sendEmail,
                sendDatabase: $sendDatabase
            ));
        }

        notify(__('panel::panel.tenancy.settings.member_invited'));

        return back();
    }

    /**
     * Handle POST request to add team member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string'],
            'send_email' => ['nullable', 'boolean'],
            'send_database' => ['nullable', 'boolean'],
        ]);

        return $this->inviteTeamMember($validated);
    }

    /**
     * Update a team member's role.
     */
    public function updateRole(Request $request, $memberId)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        if (! $this->isTeamOwner($tenant, $request->user())) {
            return back()->withErrors(['team' => 'You are not authorized to update member roles.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $membersRelationship = Str::plural('user');

        if (method_exists($tenant, $membersRelationship)) {
            $tenant->{$membersRelationship}()->updateExistingPivot($memberId, [
                'role' => $validated['role'],
            ]);
        }

        notify(__('panel::panel.tenancy.settings.role_updated'));

        return back();
    }

    /**
     * Remove a team member.
     */
    public function destroy(Request $request, $memberId)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();
        $user = $request->user();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        $isOwner = $this->isTeamOwner($tenant, $user);

        if (! $isOwner && (int) $memberId !== (int) $user->id) {
            return back()->withErrors(['team' => __('panel::panel.tenancy.settings.cannot_remove_others')]);
        }

        if (isset($tenant->owner_id) && (int) $memberId === (int) $tenant->owner_id) {
            return back()->withErrors(['team' => __('panel::panel.tenancy.settings.cannot_remove_owner')]);
        }

        $membersRelationship = Str::plural('user');

        if (method_exists($tenant, $membersRelationship)) {
            $tenant->{$membersRelationship}()->detach($memberId);
        }

        if ((int) $memberId === (int) $user->id) {
            session()->forget('laravilt.tenant_id');

            return redirect('/'.$panel->getPath());
        }

        notify(__('panel::panel.tenancy.settings.member_removed'));

        return back();
    }

    protected function getInertiaProps(): array
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();
        $user = request()->user();

        $isOwner = $this->isTeamOwner($tenant, $user);
        $members = $this->getTeamMembers($tenant);
        $availableRoles = $this->getAvailableRoles();

        return [
            'team' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
            ],
            'members' => $members,
            'isOwner' => $isOwner,
            'availableRoles' => $availableRoles,
            'permissions' => [
                'canAddTeamMembers' => $isOwner,
                'canRemoveTeamMembers' => $isOwner,
                'canUpdateMemberRole' => $isOwner,
            ],
            'routes' => [
                'addMember' => '/'.$panel->getPath().'/tenant-settings/members',
                'updateRole' => '/'.$panel->getPath().'/tenant-settings/members/{id}/role',
                'removeMember' => '/'.$panel->getPath().'/tenant-settings/members/{id}',
            ],
        ];
    }

    protected function getTeamMembers($tenant): array
    {
        if (! $tenant) {
            return [];
        }

        $membersRelationship = Str::plural('user');

        if (! method_exists($tenant, $membersRelationship)) {
            return [];
        }

        return $tenant->{$membersRelationship}()
            ->get()
            ->map(function ($member) use ($tenant) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role ?? 'member',
                    'is_owner' => isset($tenant->owner_id) && (int) $member->id === (int) $tenant->owner_id,
                ];
            })
            ->toArray();
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

    protected function getAvailableRoles(): array
    {
        return [
            [
                'key' => 'admin',
                'name' => __('panel::panel.tenancy.roles.admin'),
                'description' => __('panel::panel.tenancy.roles.admin_description'),
            ],
            [
                'key' => 'editor',
                'name' => __('panel::panel.tenancy.roles.editor'),
                'description' => __('panel::panel.tenancy.roles.editor_description'),
            ],
            [
                'key' => 'member',
                'name' => __('panel::panel.tenancy.roles.member'),
                'description' => __('panel::panel.tenancy.roles.member_description'),
            ],
        ];
    }
}
