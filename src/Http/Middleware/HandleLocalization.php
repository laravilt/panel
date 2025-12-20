<?php

namespace Laravilt\Panel\Http\Middleware;

use Closure;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HandleLocalization
{
    /**
     * RTL (Right-to-Left) locales.
     */
    protected array $rtlLocales = [
        'ar', // Arabic
        'he', // Hebrew
        'fa', // Persian/Farsi
        'ur', // Urdu
        'ps', // Pashto
        'sd', // Sindhi
        'yi', // Yiddish
        'ku', // Kurdish (some variants)
        'ug', // Uyghur
        'dv', // Divehi
    ];

    /**
     * Translation files to load for the frontend.
     * Format: 'namespace::file' for package translations or 'file' for app translations.
     */
    protected array $translationFiles = [
        'laravilt-panel::panel',     // Panel package (legacy)
        'panel::panel',              // Panel package (alias)
        'laravilt-auth::auth',       // Auth package
        'laravilt-support::support', // Support package
        'laravilt-forms::fields',    // Forms package (legacy)
        'forms::forms',              // Forms package (new)
        'laravilt-schemas::schemas', // Schemas package
        'laravilt-ai::ai',           // AI package
        'tables::tables',            // Tables package
        'actions::actions',          // Actions package
        'notifications::notifications', // Notifications package
        'widgets::widgets',          // Widgets package
        'infolists::infolists',      // Infolists package
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Get user's locale and timezone
        $locale = $user?->locale ?? config('app.locale', 'en');
        $timezone = $user?->timezone ?? config('app.timezone', 'UTC');

        // Set the application locale
        App::setLocale($locale);

        // Set the default timezone for the application
        if ($this->isValidTimezone($timezone)) {
            date_default_timezone_set($timezone);
            config(['app.timezone' => $timezone]);
        }

        // Determine if the locale is RTL
        $direction = $this->isRtlLocale($locale) ? 'rtl' : 'ltr';

        // Load translations using Laravel's translator
        $translations = $this->loadTranslations($locale);

        // Share localization data with Inertia
        Inertia::share([
            'localization' => [
                'locale' => $locale,
                'timezone' => $timezone,
                'direction' => $direction,
                'isRtl' => $direction === 'rtl',
            ],
            'translations' => $translations,
        ]);

        // Store in request for Blade templates
        $request->attributes->set('locale', $locale);
        $request->attributes->set('timezone', $timezone);
        $request->attributes->set('direction', $direction);

        // Also share with view for Blade templates
        view()->share('locale', $locale);
        view()->share('timezone', $timezone);
        view()->share('direction', $direction);

        return $next($request);
    }

    /**
     * Check if a locale is RTL.
     */
    protected function isRtlLocale(string $locale): bool
    {
        // Get the base locale (e.g., 'ar' from 'ar_EG')
        $baseLocale = explode('_', $locale)[0];
        $baseLocale = explode('-', $baseLocale)[0];

        return in_array(strtolower($baseLocale), $this->rtlLocales);
    }

    /**
     * Check if a timezone is valid.
     */
    protected function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers());
    }

    /**
     * Load translations for the given locale.
     * This flattens the PHP translation arrays into dot notation for frontend use.
     * Keys are preserved with their full namespace (e.g., 'laravilt-auth::auth.login.title').
     */
    protected function loadTranslations(string $locale): array
    {
        $translations = [];
        $translator = app('translator');

        // Load JSON translation file from lang directory (e.g., lang/en.json)
        $jsonPath = lang_path("{$locale}.json");
        if (file_exists($jsonPath)) {
            $jsonTranslations = json_decode(file_get_contents($jsonPath), true);
            if (is_array($jsonTranslations)) {
                $translations = array_merge($translations, $jsonTranslations);
            }
        }

        // Load PHP translation files from lang/{locale}/ directory
        $phpLangPath = lang_path("{$locale}");
        if (is_dir($phpLangPath)) {
            foreach (glob("{$phpLangPath}/*.php") as $file) {
                $fileName = basename($file, '.php');
                $fileTranslations = require $file;
                if (is_array($fileTranslations)) {
                    $flattened = $this->flattenTranslations($fileTranslations, $fileName);
                    $translations = array_merge($translations, $flattened);
                }
            }
        }

        // Load PHP translation files from packages and flatten them
        foreach ($this->translationFiles as $file) {
            // Use Laravel's translator to get the translation array
            // Lang::get() with '*' returns all translations for the file
            $fileTranslations = $translator->get($file, [], $locale);

            // If it's an array, flatten it with the namespace prefix
            if (is_array($fileTranslations)) {
                // Prefix with the full namespace (e.g., 'laravilt-auth::auth')
                $flattened = $this->flattenTranslations($fileTranslations, $file);
                $translations = array_merge($translations, $flattened);
            }
        }

        return $translations;
    }

    /**
     * Flatten a nested array to dot notation keys.
     * Example: ['common' => ['save' => 'Save']] becomes ['common.save' => 'Save']
     */
    protected function flattenTranslations(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                // Recursively flatten nested arrays
                $result = array_merge($result, $this->flattenTranslations($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
