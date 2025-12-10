<?php

namespace Laravilt\Panel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravilt\Auth\Pages\LocaleTimezone;
use Laravilt\Panel\PanelRegistry;
use Symfony\Component\HttpFoundation\Response;

class SharePanelData
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $registry = app(PanelRegistry::class);
        $panel = $registry->getCurrent();

        if ($panel) {
            Inertia::share([
                'panel' => fn () => [
                    'id' => $panel->getId(),
                    'path' => $panel->getPath(),
                    'brandName' => $panel->getBrandName(),
                    'brandLogo' => $panel->getBrandLogo(),
                    'navigation' => $panel->getNavigation(),
                    'userMenu' => $panel->getUserMenu(),
                    'user' => $request->user() ? [
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                    ] : null,
                    'auth' => [
                        'hasProfile' => $panel->hasProfile(),
                        'hasLogin' => $panel->hasLogin(),
                        'hasRegistration' => $panel->hasRegistration(),
                        'hasPasswordReset' => $panel->hasPasswordReset(),
                        'hasEmailVerification' => $panel->hasEmailVerification(),
                        'hasOtp' => $panel->hasOtp(),
                    ],
                    'hasDatabaseNotifications' => $panel->hasDatabaseNotifications(),
                    'databaseNotificationsPolling' => $panel->getDatabaseNotificationsPolling(),
                    'availableLocales' => $this->getAvailableLocales(),
                    'currentLocale' => $request->user()?->locale ?? config('app.locale', 'en'),
                    'font' => $panel->getFontData(),
                    'hasDarkMode' => $panel->hasDarkMode(),
                ],
                'notifications' => fn () => $this->getNotifications($request),
                'databaseNotifications' => fn () => $this->getDatabaseNotifications($request),
            ]);
        }

        return $next($request);
    }

    /**
     * Get notifications from session (supports multiple session keys).
     */
    protected function getNotifications(Request $request): array
    {
        $notifications = [];

        // Check for notifications passed via redirect->with() (most reliable method)
        $redirectNotifications = $request->session()->pull('_laravilt_notifications', []);
        if ($redirectNotifications) {
            $notifications = array_merge($notifications, $redirectNotifications);
        }

        // Check for single notification from Notification::send()
        $laraviltNotification = $request->session()->pull('laravilt.notification');
        if ($laraviltNotification) {
            $notifications[] = $laraviltNotification;
        }

        // Also check for legacy 'notifications' key (array of notifications)
        $legacyNotifications = $request->session()->pull('notifications', []);
        if ($legacyNotifications) {
            $notifications = array_merge($notifications, $legacyNotifications);
        }

        return $notifications;
    }

    /**
     * Get database notifications for the authenticated user.
     */
    protected function getDatabaseNotifications(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'notifications')) {
            return [
                'notifications' => [],
                'unreadCount' => 0,
            ];
        }

        $notifications = $user->notifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($notification) => $this->formatDatabaseNotification($notification))
            ->toArray();

        $unreadCount = $user->unreadNotifications()->count();

        return [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ];
    }

    /**
     * Format a database notification for the frontend.
     */
    protected function formatDatabaseNotification($notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? ($data['message'] ?? null),
            'icon' => $data['icon'] ?? null,
            'iconColor' => $data['icon_color'] ?? ($data['color'] ?? null),
            'status' => $data['status'] ?? 'info',
            'color' => $data['color'] ?? null,
            'actions' => $data['actions'] ?? [],
            'data' => $data['data'] ?? $data,
            'readAt' => $notification->read_at?->toISOString(),
            'createdAt' => $notification->created_at?->toISOString(),
            'humanTime' => $notification->created_at?->diffForHumans(),
        ];
    }

    /**
     * Get available locales with flags.
     */
    protected function getAvailableLocales(): array
    {
        // Map locale codes to country codes for flags
        $flagMap = [
            'en' => 'us',
            'es' => 'es',
            'fr' => 'fr',
            'de' => 'de',
            'it' => 'it',
            'pt' => 'pt',
            'ru' => 'ru',
            'zh' => 'cn',
            'ja' => 'jp',
            'ko' => 'kr',
            'ar' => 'sa',
            'he' => 'il',
            'fa' => 'ir',
            'ur' => 'pk',
            'hi' => 'in',
            'nl' => 'nl',
            'pl' => 'pl',
            'tr' => 'tr',
        ];

        // Get available locales from LocaleTimezone page or config
        if (class_exists(LocaleTimezone::class)) {
            $locales = LocaleTimezone::getAvailableLocales();

            return array_map(function ($locale) use ($flagMap) {
                $locale['flag'] = $flagMap[$locale['value']] ?? $locale['value'];

                return $locale;
            }, $locales);
        }

        // Fallback locales
        return [
            ['value' => 'en', 'label' => 'English', 'dir' => 'ltr', 'flag' => 'us'],
            ['value' => 'ar', 'label' => 'العربية', 'dir' => 'rtl', 'flag' => 'sa'],
        ];
    }
}
