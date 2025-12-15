<?php

namespace Laravilt\Panel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;

class TenantSettingsController extends Controller
{
    /**
     * Show the team settings page.
     */
    public function show(Request $request)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return redirect('/'.$panel->getPath());
        }

        $user = $request->user();
        $slugAttribute = $panel->getTenantSlugAttribute();

        // Get team members with roles
        $members = $this->getTeamMembers($tenant, $panel);

        // Check if user is owner
        $isOwner = $this->isTeamOwner($tenant, $user);

        // Get available roles
        $availableRoles = $this->getAvailableRoles($panel);

        return Inertia::render('Tenant/Settings', [
            'panel' => [
                'id' => $panel->getId(),
                'path' => $panel->getPath(),
            ],
            'team' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
                'slug' => $tenant->{$slugAttribute},
                'owner_id' => $tenant->owner_id ?? null,
            ],
            'members' => $members,
            'isOwner' => $isOwner,
            'availableRoles' => $availableRoles,
            'permissions' => [
                'canUpdateTeam' => $isOwner,
                'canDeleteTeam' => $isOwner,
                'canAddTeamMembers' => $isOwner,
                'canRemoveTeamMembers' => $isOwner,
            ],
        ]);
    }

    /**
     * Update the team name.
     */
    public function updateName(Request $request)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        // Check if user can update team
        if (! $this->isTeamOwner($tenant, $request->user())) {
            return back()->withErrors(['team' => 'You are not authorized to update this team.']);
        }

        $slugAttribute = $panel->getTenantSlugAttribute();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant->name = $validated['name'];
        $tenant->save();

        return back()->with('success', 'Team name updated successfully.');
    }

    /**
     * Invite a new team member.
     */
    public function inviteMember(Request $request)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        if (! $this->isTeamOwner($tenant, $request->user())) {
            return back()->withErrors(['team' => 'You are not authorized to invite members.']);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string'],
        ]);

        // Find the user by email
        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        $invitedUser = $userModel::where('email', $validated['email'])->first();

        if (! $invitedUser) {
            return back()->withErrors(['email' => 'No user found with this email address.']);
        }

        // Check if user is already a member
        $ownershipRelationship = $panel->getTenantOwnershipRelationship();
        $pluralRelationship = Str::plural($ownershipRelationship);

        if (method_exists($invitedUser, $pluralRelationship)) {
            $existingMembership = $invitedUser->{$pluralRelationship}()
                ->where($tenant->getTable().'.id', $tenant->getKey())
                ->exists();

            if ($existingMembership) {
                return back()->withErrors(['email' => 'This user is already a member of this team.']);
            }

            // Add user to team with role
            $invitedUser->{$pluralRelationship}()->attach($tenant->getKey(), ['role' => $validated['role']]);
        }

        return back()->with('success', 'Team member added successfully.');
    }

    /**
     * Update a team member's role.
     */
    public function updateMemberRole(Request $request, $memberId)
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

        $ownershipRelationship = $panel->getTenantOwnershipRelationship();
        $membersRelationship = Str::plural('user');

        // Update the role in the pivot table
        if (method_exists($tenant, $membersRelationship)) {
            $tenant->{$membersRelationship}()->updateExistingPivot($memberId, [
                'role' => $validated['role'],
            ]);
        }

        return back()->with('success', 'Member role updated successfully.');
    }

    /**
     * Remove a team member.
     */
    public function removeMember(Request $request, $memberId)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();
        $user = $request->user();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        // Owner can remove anyone, users can only remove themselves
        $isOwner = $this->isTeamOwner($tenant, $user);

        if (! $isOwner && (int) $memberId !== (int) $user->id) {
            return back()->withErrors(['team' => 'You can only remove yourself from the team.']);
        }

        // Cannot remove owner
        if (isset($tenant->owner_id) && (int) $memberId === (int) $tenant->owner_id) {
            return back()->withErrors(['team' => 'Cannot remove the team owner.']);
        }

        $ownershipRelationship = $panel->getTenantOwnershipRelationship();
        $membersRelationship = Str::plural('user');

        // Remove from pivot table
        if (method_exists($tenant, $membersRelationship)) {
            $tenant->{$membersRelationship}()->detach($memberId);
        }

        // If user removed themselves, redirect to panel
        if ((int) $memberId === (int) $user->id) {
            session()->forget('laravilt.tenant_id');

            return redirect('/'.$panel->getPath());
        }

        return back()->with('success', 'Team member removed successfully.');
    }

    /**
     * Delete the team.
     */
    public function destroy(Request $request)
    {
        $panel = Panel::getCurrent();
        $tenant = Laravilt::getTenant();
        $user = $request->user();

        if (! $tenant) {
            return back()->withErrors(['team' => 'No team selected.']);
        }

        if (! $this->isTeamOwner($tenant, $user)) {
            return back()->withErrors(['team' => 'You are not authorized to delete this team.']);
        }

        // Clear session before deleting
        session()->forget('laravilt.tenant_id');

        // Delete the team
        $tenant->delete();

        return redirect('/'.$panel->getPath())->with('success', 'Team deleted successfully.');
    }

    /**
     * Get team members with their roles.
     */
    protected function getTeamMembers($tenant, $panel): array
    {
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

    /**
     * Check if user is the team owner.
     */
    protected function isTeamOwner($tenant, $user): bool
    {
        if (! $user) {
            return false;
        }

        // Check via owner_id
        if (isset($tenant->owner_id)) {
            return (int) $tenant->owner_id === (int) $user->id;
        }

        // Check via pivot role
        $ownershipRelationship = Panel::getCurrent()->getTenantOwnershipRelationship();
        $pluralRelationship = Str::plural($ownershipRelationship);

        if (method_exists($user, $pluralRelationship)) {
            $membership = $user->{$pluralRelationship}()
                ->where($tenant->getTable().'.id', $tenant->getKey())
                ->first();

            return $membership && ($membership->pivot->role ?? '') === 'owner';
        }

        return false;
    }

    /**
     * Get available roles for team members.
     */
    protected function getAvailableRoles($panel): array
    {
        // Default Jetstream-like roles
        return [
            [
                'key' => 'admin',
                'name' => 'Administrator',
                'description' => 'Administrators can perform any action.',
            ],
            [
                'key' => 'editor',
                'name' => 'Editor',
                'description' => 'Editors can create, read, and update resources.',
            ],
            [
                'key' => 'member',
                'name' => 'Member',
                'description' => 'Members can read resources.',
            ],
        ];
    }
}
