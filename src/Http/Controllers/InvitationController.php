<?php

namespace Laravilt\Panel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Laravilt\Panel\Facades\Panel;

class InvitationController extends Controller
{
    /**
     * Accept a team invitation.
     */
    public function accept(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(401, __('panel::panel.tenancy.notifications.invalid_invitation_link'));
        }

        $teamId = $request->route('team');
        $userId = $request->route('user');
        $panelPath = $request->route('panel');

        $user = $request->user();

        // Verify the invitation is for the current user
        if (! $user || (int) $user->id !== (int) $userId) {
            return redirect('/' . $panelPath . '/login')
                ->withErrors(['email' => __('panel::panel.tenancy.notifications.login_required')]);
        }

        // Get the team model from panel configuration
        $panel = Panel::getByPath($panelPath);
        if (! $panel) {
            abort(404);
        }

        $tenantModel = $panel->getTenantModel();
        if (! $tenantModel) {
            abort(404);
        }

        $team = $tenantModel::find($teamId);
        if (! $team) {
            abort(404, __('panel::panel.tenancy.notifications.team_not_found'));
        }

        // Check if user is already a member
        $membersRelationship = Str::plural('user');
        if (method_exists($team, $membersRelationship)) {
            $isMember = $team->{$membersRelationship}()
                ->where('users.id', $user->id)
                ->exists();

            if ($isMember) {
                // Already a member, just redirect to the team
                session(['laravilt.tenant_id' => $team->getKey()]);

                notify(__('panel::panel.tenancy.notifications.already_member'));

                return redirect('/' . $panelPath);
            }
        }

        notify(__('panel::panel.tenancy.notifications.invitation_accepted'));

        // Set the team as the current tenant and redirect
        session(['laravilt.tenant_id' => $team->getKey()]);

        return redirect('/' . $panelPath);
    }

    /**
     * Decline a team invitation.
     */
    public function decline(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(401, __('panel::panel.tenancy.notifications.invalid_invitation_link'));
        }

        $teamId = $request->route('team');
        $userId = $request->route('user');
        $panelPath = $request->route('panel');

        $user = $request->user();

        // Verify the invitation is for the current user
        if (! $user || (int) $user->id !== (int) $userId) {
            return redirect('/' . $panelPath . '/login')
                ->withErrors(['email' => __('panel::panel.tenancy.notifications.login_required')]);
        }

        // Get the team model from panel configuration
        $panel = Panel::getByPath($panelPath);
        if (! $panel) {
            abort(404);
        }

        $tenantModel = $panel->getTenantModel();
        if (! $tenantModel) {
            abort(404);
        }

        $team = $tenantModel::find($teamId);
        if (! $team) {
            abort(404, __('panel::panel.tenancy.notifications.team_not_found'));
        }

        // Remove the user from the team if they were added
        $membersRelationship = Str::plural('user');
        if (method_exists($team, $membersRelationship)) {
            $team->{$membersRelationship}()->detach($user->id);
        }

        notify(__('panel::panel.tenancy.notifications.invitation_declined'));

        return redirect('/' . $panelPath);
    }
}
