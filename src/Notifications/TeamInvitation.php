<?php

namespace Laravilt\Panel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

/**
 * Team Invitation Notification
 *
 * Sends a notification to a user when they are invited to join a team.
 * Can be sent via email and/or database notification.
 */
class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The team the user is being invited to.
     */
    public Model $team;

    /**
     * The user who sent the invitation.
     */
    public Model $inviter;

    /**
     * The role the user is being assigned.
     */
    public string $role;

    /**
     * The panel path for building URLs.
     */
    public string $panelPath;

    /**
     * Whether to send email notification.
     */
    public bool $sendEmail;

    /**
     * Whether to send database notification.
     */
    public bool $sendDatabase;

    /**
     * The invited user (set when sending).
     */
    protected ?Model $invitedUser = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Model $team,
        Model $inviter,
        string $role,
        string $panelPath,
        bool $sendEmail = true,
        bool $sendDatabase = true
    ) {
        $this->team = $team;
        $this->inviter = $inviter;
        $this->role = $role;
        $this->panelPath = $panelPath;
        $this->sendEmail = $sendEmail;
        $this->sendDatabase = $sendDatabase;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = [];

        if ($this->sendDatabase) {
            $channels[] = 'database';
        }

        if ($this->sendEmail) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $teamName = $this->team->name ?? 'a team';
        $inviterName = $this->inviter->name ?? 'Someone';
        $roleName = $this->getRoleName();
        $acceptUrl = $this->getAcceptUrl($notifiable);

        return (new MailMessage)
            ->subject(Lang::get('panel::panel.tenancy.notifications.invitation_subject', [
                'team' => $teamName,
            ]))
            ->greeting(Lang::get('panel::panel.tenancy.notifications.invitation_greeting', [
                'name' => $notifiable->name ?? 'there',
            ]))
            ->line(Lang::get('panel::panel.tenancy.notifications.invitation_line1', [
                'inviter' => $inviterName,
                'team' => $teamName,
            ]))
            ->line(Lang::get('panel::panel.tenancy.notifications.invitation_role', [
                'role' => $roleName,
            ]))
            ->action(
                Lang::get('panel::panel.tenancy.notifications.accept_invitation'),
                $acceptUrl
            )
            ->line(Lang::get('panel::panel.tenancy.notifications.invitation_footer'))
            ->salutation(Lang::get('panel::panel.tenancy.notifications.salutation'));
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $teamName = $this->team->name ?? 'a team';
        $inviterName = $this->inviter->name ?? 'Someone';
        $roleName = $this->getRoleName();

        return [
            'title' => Lang::get('panel::panel.tenancy.notifications.invitation_title'),
            'body' => Lang::get('panel::panel.tenancy.notifications.invitation_body', [
                'inviter' => $inviterName,
                'team' => $teamName,
                'role' => $roleName,
            ]),
            'icon' => 'users',
            'icon_color' => 'primary',
            'status' => 'info',
            'color' => 'primary',
            'actions' => [
                [
                    'label' => Lang::get('panel::panel.tenancy.notifications.accept_invitation'),
                    'url' => $this->getAcceptUrl($notifiable),
                    'variant' => 'default',
                ],
                [
                    'label' => Lang::get('panel::panel.tenancy.notifications.decline_invitation'),
                    'url' => $this->getDeclineUrl($notifiable),
                    'variant' => 'outline',
                ],
            ],
            'data' => [
                'type' => 'team_invitation',
                'team_id' => $this->team->getKey(),
                'team_name' => $teamName,
                'inviter_id' => $this->inviter->getKey(),
                'inviter_name' => $inviterName,
                'role' => $this->role,
            ],
            'format' => 'laravilt',
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Get the human-readable role name.
     */
    protected function getRoleName(): string
    {
        return match ($this->role) {
            'owner' => Lang::get('panel::panel.tenancy.roles.owner'),
            'admin' => Lang::get('panel::panel.tenancy.roles.admin'),
            'editor' => Lang::get('panel::panel.tenancy.roles.editor'),
            'member' => Lang::get('panel::panel.tenancy.roles.member'),
            default => $this->role,
        };
    }

    /**
     * Get the URL to accept the invitation.
     */
    protected function getAcceptUrl(mixed $notifiable): string
    {
        return URL::signedRoute('laravilt.invitation.accept', [
            'team' => $this->team->getKey(),
            'user' => $notifiable->getKey(),
            'panel' => $this->panelPath,
        ]);
    }

    /**
     * Get the URL to decline the invitation.
     */
    protected function getDeclineUrl(mixed $notifiable): string
    {
        return URL::signedRoute('laravilt.invitation.decline', [
            'team' => $this->team->getKey(),
            'user' => $notifiable->getKey(),
            'panel' => $this->panelPath,
        ]);
    }
}
