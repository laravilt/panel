<?php

declare(strict_types=1);

namespace Laravilt\Panel\Concerns;

use Closure;

trait HasNotifications
{
    protected bool $hasDatabaseNotifications = false;

    protected string|Closure|null $databaseNotificationsPolling = '30s';

    protected bool $hasApiNotifications = false;

    /**
     * Enable database notifications for this panel.
     */
    public function databaseNotifications(bool $condition = true): static
    {
        $this->hasDatabaseNotifications = $condition;

        return $this;
    }

    /**
     * Check if database notifications are enabled.
     */
    public function hasDatabaseNotifications(): bool
    {
        return $this->hasDatabaseNotifications;
    }

    /**
     * Set the polling interval for database notifications.
     */
    public function databaseNotificationsPolling(string|Closure|null $interval): static
    {
        $this->databaseNotificationsPolling = $interval;

        return $this;
    }

    /**
     * Get the polling interval for database notifications.
     */
    public function getDatabaseNotificationsPolling(): ?string
    {
        if ($this->databaseNotificationsPolling instanceof Closure) {
            return ($this->databaseNotificationsPolling)();
        }

        return $this->databaseNotificationsPolling;
    }

    /**
     * Enable API notifications endpoints.
     */
    public function apiNotifications(bool $condition = true): static
    {
        $this->hasApiNotifications = $condition;

        return $this;
    }

    /**
     * Check if API notifications are enabled.
     */
    public function hasApiNotifications(): bool
    {
        return $this->hasApiNotifications;
    }

    /**
     * Register notification routes for this panel.
     */
    protected function registerNotificationRoutes(): void
    {
        if (! $this->hasDatabaseNotifications && ! $this->hasApiNotifications) {
            return;
        }

        $panelPath = $this->getPath();
        $panelId = $this->getId();

        \Illuminate\Support\Facades\Route::middleware($this->getMiddleware())
            ->prefix($panelPath.'/notifications')
            ->name($panelId.'.notifications.')
            ->group(function () {
                $controller = \Laravilt\Notifications\Http\Controllers\NotificationController::class;

                \Illuminate\Support\Facades\Route::get('/', [$controller, 'index'])->name('index');
                \Illuminate\Support\Facades\Route::get('/unread', [$controller, 'unread'])->name('unread');
                \Illuminate\Support\Facades\Route::post('/{id}/read', [$controller, 'markAsRead'])->name('mark-as-read');
                \Illuminate\Support\Facades\Route::post('/read-all', [$controller, 'markAllAsRead'])->name('mark-all-as-read');
                \Illuminate\Support\Facades\Route::delete('/{id}', [$controller, 'destroy'])->name('destroy');
                \Illuminate\Support\Facades\Route::delete('/', [$controller, 'destroyAll'])->name('destroy-all');
            });
    }
}
