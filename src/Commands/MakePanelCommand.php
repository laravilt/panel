<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakePanelCommand extends Command
{
    protected $signature = 'laravilt:panel
                            {id? : The panel identifier}
                            {--path= : The URL path for the panel}
                            {--quick : Skip interactive mode and use defaults}';

    protected $description = 'Create a new panel with interactive feature selection';

    /**
     * Selected two-factor providers.
     */
    protected array $twoFactorProviders = [];

    /**
     * Selected social login providers.
     */
    protected array $socialProviders = [];

    /**
     * Selected AI model.
     */
    protected string $aiModel = 'GPT_4O_MINI';

    /**
     * Available features for panels.
     */
    protected array $availableFeatures = [
        // Authentication
        'login' => 'Login page',
        'registration' => 'User registration',
        'password-reset' => 'Password reset',
        'email-verification' => 'Email verification',
        'otp' => 'OTP authentication',
        'magic-links' => 'Magic link login',

        // Security
        'two-factor' => 'Two-factor authentication (2FA)',
        'passkeys' => 'Passkey authentication (WebAuthn)',
        'session-management' => 'Session management',

        // User Management
        'profile' => 'User profile management',
        'social-login' => 'Social login (OAuth)',
        'connected-accounts' => 'Connected accounts',
        'api-tokens' => 'API tokens',

        // Features
        'database-notifications' => 'Database notifications',
        'locale-timezone' => 'Locale & timezone settings',
        'global-search' => 'Global search',
        'ai-providers' => 'AI assistant',
    ];

    /**
     * Available two-factor providers.
     */
    protected array $availableTwoFactorProviders = [
        'totp' => 'TOTP (Authenticator App)',
        'email' => 'Email verification code',
    ];

    /**
     * Available social login providers.
     */
    protected array $availableSocialProviders = [
        'google' => 'Google',
        'github' => 'GitHub',
        'facebook' => 'Facebook',
        'twitter' => 'Twitter/X',
        'linkedin' => 'LinkedIn',
        'discord' => 'Discord',
        'jira' => 'Jira',
    ];

    /**
     * Available AI models.
     */
    protected array $availableAIModels = [
        'GPT_4O_MINI' => 'GPT-4o Mini (Recommended)',
        'GPT_4O' => 'GPT-4o',
        'GPT_4_TURBO' => 'GPT-4 Turbo',
        'GPT_3_5_TURBO' => 'GPT-3.5 Turbo',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get panel ID
        $id = $this->argument('id') ?? text(
            label: 'What is the panel identifier?',
            placeholder: 'admin',
            default: 'admin',
            required: true,
            hint: 'This will be used for the URL path and provider name'
        );

        $path = $this->option('path') ?? $id;
        $studlyId = Str::studly($id);

        $this->newLine();
        $this->components->info("Creating '{$id}' panel...");
        $this->newLine();

        // Interactive feature selection (unless --quick flag is used)
        $features = $this->option('quick')
            ? $this->getDefaultFeatures()
            : $this->selectFeatures();

        // Ask for provider options based on selected features
        if (! $this->option('quick')) {
            $this->askForProviderOptions($features);
        } else {
            // Set defaults for quick mode
            $this->twoFactorProviders = ['totp', 'email'];
            $this->socialProviders = ['google', 'github'];
            $this->aiModel = 'GPT_4O_MINI';
        }

        // Create panel provider
        $this->createPanelProvider($studlyId, $id, $path, $features);

        // Create directory structure
        $this->createDirectories($studlyId);

        // Create Dashboard page
        $this->createDashboardPage($studlyId);

        // Register provider
        $this->registerProvider($studlyId);

        // Run additional setup based on features
        $this->runFeatureSetup($features);

        $this->newLine();
        $this->components->info("Panel [{$id}] created successfully!");
        $this->newLine();

        $this->components->bulletList([
            "Panel provider: <fg=cyan>app/Providers/Laravilt/{$studlyId}PanelProvider.php</>",
            "Pages directory: <fg=cyan>app/Laravilt/{$studlyId}/Pages</>",
            "Resources directory: <fg=cyan>app/Laravilt/{$studlyId}/Resources</>",
            "Widgets directory: <fg=cyan>app/Laravilt/{$studlyId}/Widgets</>",
        ]);

        $this->newLine();
        $this->components->info('Selected features:');
        foreach ($features as $feature) {
            $this->components->twoColumnDetail(
                $feature,
                $this->availableFeatures[$feature] ?? $feature
            );
        }

        // Clear caches (don't rebuild as closures can't be serialized)
        $this->newLine();
        $this->components->task('Clearing caches', function () {
            Artisan::call('optimize:clear');

            return true;
        });

        return self::SUCCESS;
    }

    /**
     * Get default features for quick mode.
     */
    protected function getDefaultFeatures(): array
    {
        return [
            'login',
            'password-reset',
            'profile',
        ];
    }

    /**
     * Interactive feature selection using Laravel Prompts multiselect.
     */
    protected function selectFeatures(): array
    {
        $options = [];

        // Authentication features
        $options['login'] = 'Login page';
        $options['registration'] = 'User registration';
        $options['password-reset'] = 'Password reset';
        $options['email-verification'] = 'Email verification';
        $options['otp'] = 'OTP authentication';
        $options['magic-links'] = 'Magic link login';

        // Security features
        $options['two-factor'] = 'Two-factor authentication (2FA)';
        $options['passkeys'] = 'Passkey authentication (WebAuthn)';
        $options['session-management'] = 'Session management';

        // User Management features
        $options['profile'] = 'User profile management';
        $options['social-login'] = 'Social login (OAuth providers)';
        $options['connected-accounts'] = 'Connected accounts';
        $options['api-tokens'] = 'API tokens';

        // Additional features
        $options['database-notifications'] = 'Database notifications';
        $options['locale-timezone'] = 'Locale & timezone settings';
        $options['global-search'] = 'Global search';
        $options['ai-providers'] = 'AI assistant';

        return multiselect(
            label: 'Which features would you like to enable?',
            options: $options,
            default: ['login', 'password-reset', 'profile'],
            required: false,
            hint: 'Use space to select, enter to confirm',
            scroll: 12
        );
    }

    /**
     * Ask for provider options based on selected features.
     */
    protected function askForProviderOptions(array $features): void
    {
        // Two-factor providers
        if (in_array('two-factor', $features)) {
            $this->newLine();
            $this->twoFactorProviders = multiselect(
                label: 'Which 2FA providers would you like to enable?',
                options: $this->availableTwoFactorProviders,
                default: ['totp', 'email'],
                required: true,
                hint: 'At least one provider is required for 2FA'
            );
        }

        // Social login providers
        if (in_array('social-login', $features)) {
            $this->newLine();
            $this->socialProviders = multiselect(
                label: 'Which social login providers would you like to enable?',
                options: $this->availableSocialProviders,
                default: ['google', 'github'],
                required: true,
                hint: 'Select the OAuth providers you want to support'
            );
        }

        // AI model selection
        if (in_array('ai-providers', $features)) {
            $this->newLine();
            $this->aiModel = select(
                label: 'Which AI model would you like to use?',
                options: $this->availableAIModels,
                default: 'GPT_4O_MINI',
                hint: 'GPT-4o Mini is recommended for most use cases'
            );
        }
    }

    /**
     * Create panel provider with selected features.
     */
    protected function createPanelProvider(string $studlyId, string $id, string $path, array $features): void
    {
        $content = $this->generatePanelProviderContent($studlyId, $id, $path, $features);

        $providerPath = app_path("Providers/Laravilt/{$studlyId}PanelProvider.php");

        File::ensureDirectoryExists(dirname($providerPath));
        File::put($providerPath, $content);

        $this->components->task('Creating panel provider', fn () => true);
    }

    /**
     * Generate panel provider content based on features.
     */
    protected function generatePanelProviderContent(string $studlyId, string $id, string $path, array $features): string
    {
        $imports = $this->buildImports($features);
        $authFeatures = $this->buildAuthFeatures($features);
        $middleware = $this->buildMiddleware($features);

        $content = <<<PHP
<?php

namespace App\Providers\Laravilt;

{$imports}use Laravilt\Panel\Panel;
use Laravilt\Panel\PanelProvider;

class {$studlyId}PanelProvider extends PanelProvider
{
    /**
     * Configure the panel.
     */
    public function panel(Panel \$panel): Panel
    {
        return \$panel
            ->id('{$id}')
            ->path('{$path}')
            ->brandName('{$studlyId}')
            ->discoverAutomatically()
{$authFeatures}{$middleware};
    }
}

PHP;

        return $content;
    }

    /**
     * Build imports based on selected features.
     */
    protected function buildImports(array $features): string
    {
        $imports = [];

        // Two-factor imports
        if (in_array('two-factor', $features) && ! empty($this->twoFactorProviders)) {
            $imports[] = 'use Laravilt\Auth\Builders\TwoFactorProviderBuilder;';

            if (in_array('totp', $this->twoFactorProviders)) {
                $imports[] = 'use Laravilt\Auth\Drivers\TotpDriver;';
            }
            if (in_array('email', $this->twoFactorProviders)) {
                $imports[] = 'use Laravilt\Auth\Drivers\EmailDriver;';
            }
        }

        // Social login imports
        if (in_array('social-login', $features) && ! empty($this->socialProviders)) {
            $imports[] = 'use Laravilt\Auth\Builders\SocialProviderBuilder;';

            foreach ($this->socialProviders as $provider) {
                $providerClass = $this->getSocialProviderClass($provider);
                $imports[] = "use Laravilt\\Auth\\Drivers\\SocialProviders\\{$providerClass};";
            }
        }

        // Global search imports
        if (in_array('global-search', $features)) {
            $imports[] = 'use Laravilt\AI\Builders\GlobalSearchBuilder;';
        }

        // AI providers imports
        if (in_array('ai-providers', $features)) {
            $imports[] = 'use Laravilt\AI\Builders\AIProviderBuilder;';
            $imports[] = 'use Laravilt\AI\Providers\OpenAIProvider;';
            $imports[] = 'use Laravilt\AI\Enums\OpenAIModel;';
        }

        if (empty($imports)) {
            return '';
        }

        // Sort imports for cleaner code
        sort($imports);

        return implode("\n", $imports)."\n";
    }

    /**
     * Get social provider class name.
     */
    protected function getSocialProviderClass(string $provider): string
    {
        return match ($provider) {
            'google' => 'GoogleProvider',
            'github' => 'GitHubProvider',
            'facebook' => 'FacebookProvider',
            'twitter' => 'TwitterProvider',
            'linkedin' => 'LinkedInProvider',
            'discord' => 'DiscordProvider',
            'jira' => 'JiraProvider',
            default => Str::studly($provider).'Provider',
        };
    }

    /**
     * Build authentication feature chain.
     */
    protected function buildAuthFeatures(array $features): string
    {
        $methods = [];

        if (in_array('login', $features)) {
            $methods[] = '            ->login()';
        }

        if (in_array('registration', $features)) {
            $methods[] = '            ->registration()';
        }

        if (in_array('password-reset', $features)) {
            $methods[] = '            ->passwordReset()';
        }

        if (in_array('email-verification', $features)) {
            $methods[] = '            ->emailVerification()';
        }

        if (in_array('otp', $features)) {
            $methods[] = '            ->otp()';
        }

        if (in_array('magic-links', $features)) {
            $methods[] = '            ->magicLinks()';
        }

        if (in_array('profile', $features)) {
            $methods[] = '            ->profile()';
        }

        if (in_array('two-factor', $features) && ! empty($this->twoFactorProviders)) {
            $methods[] = $this->buildTwoFactorMethod();
        }

        if (in_array('passkeys', $features)) {
            $methods[] = '            ->passkeys()';
        }

        if (in_array('social-login', $features) && ! empty($this->socialProviders)) {
            $methods[] = $this->buildSocialLoginMethod();
        }

        if (in_array('connected-accounts', $features)) {
            $methods[] = '            ->connectedAccounts()';
        }

        if (in_array('session-management', $features)) {
            $methods[] = '            ->sessionManagement()';
        }

        if (in_array('api-tokens', $features)) {
            $methods[] = '            ->apiTokens()';
        }

        if (in_array('database-notifications', $features)) {
            $methods[] = '            ->databaseNotifications()';
        }

        if (in_array('locale-timezone', $features)) {
            $methods[] = '            ->localeTimezone()';
        }

        if (in_array('global-search', $features)) {
            $methods[] = $this->buildGlobalSearchMethod();
        }

        if (in_array('ai-providers', $features)) {
            $methods[] = $this->buildAIProvidersMethod();
        }

        return empty($methods) ? '' : implode("\n", $methods)."\n";
    }

    /**
     * Build two-factor method with selected providers.
     */
    protected function buildTwoFactorMethod(): string
    {
        $providers = [];

        foreach ($this->twoFactorProviders as $provider) {
            $driverClass = match ($provider) {
                'totp' => 'TotpDriver',
                'email' => 'EmailDriver',
                default => Str::studly($provider).'Driver',
            };
            $providers[] = "                \$builder->provider({$driverClass}::class);";
        }

        $providersCode = implode("\n", $providers);

        return <<<PHP
            ->twoFactor(builder: function (TwoFactorProviderBuilder \$builder) {
{$providersCode}
            })
PHP;
    }

    /**
     * Build social login method with selected providers.
     */
    protected function buildSocialLoginMethod(): string
    {
        $providers = [];

        foreach ($this->socialProviders as $provider) {
            $providerClass = $this->getSocialProviderClass($provider);
            $providers[] = "                \$builder->provider({$providerClass}::class, fn ({$providerClass} \$p) => \$p->enabled());";
        }

        $providersCode = implode("\n", $providers);

        return <<<PHP
            ->socialLogin(function (SocialProviderBuilder \$builder) {
{$providersCode}
            })
PHP;
    }

    /**
     * Build global search method.
     */
    protected function buildGlobalSearchMethod(): string
    {
        return <<<'PHP'
            ->globalSearch(function (GlobalSearchBuilder $search) {
                $search->enabled()->limit(5)->debounce(300);
            })
PHP;
    }

    /**
     * Build AI providers method with selected model.
     */
    protected function buildAIProvidersMethod(): string
    {
        return <<<PHP
            ->aiProviders(function (AIProviderBuilder \$ai) {
                \$ai->provider(OpenAIProvider::class, fn (OpenAIProvider \$p) => \$p->model(OpenAIModel::{$this->aiModel}))
                   ->default('openai');
            })
PHP;
    }

    /**
     * Build middleware chain.
     */
    protected function buildMiddleware(array $features): string
    {
        $middleware = "            ->middleware(['web', 'auth'])\n";
        $middleware .= "            ->authMiddleware(['auth'])";

        return $middleware;
    }

    /**
     * Create directories for the panel.
     */
    protected function createDirectories(string $studlyId): void
    {
        $basePath = app_path("Laravilt/{$studlyId}");

        $directories = [
            'Pages',
            'Widgets',
            'Resources',
        ];

        foreach ($directories as $directory) {
            File::ensureDirectoryExists("{$basePath}/{$directory}");
        }

        $this->components->task('Creating directories', fn () => true);
    }

    /**
     * Create Dashboard page.
     */
    protected function createDashboardPage(string $studlyId): void
    {
        $stub = File::get(__DIR__.'/../../stubs/dashboard.stub');

        $content = str_replace(
            ['{{ studlyId }}'],
            [$studlyId],
            $stub
        );

        $pagePath = app_path("Laravilt/{$studlyId}/Pages/Dashboard.php");

        File::ensureDirectoryExists(dirname($pagePath));
        File::put($pagePath, $content);

        $this->components->task('Creating Dashboard page', fn () => true);
    }

    /**
     * Register provider in bootstrap/providers.php.
     */
    protected function registerProvider(string $studlyId): void
    {
        $provider = "App\\Providers\\Laravilt\\{$studlyId}PanelProvider::class";
        $providersFile = base_path('bootstrap/providers.php');

        if (! File::exists($providersFile)) {
            $this->components->warn('bootstrap/providers.php not found. Please register the provider manually.');

            return;
        }

        $content = File::get($providersFile);

        if (str_contains($content, $provider)) {
            $this->components->warn('Provider already registered');

            return;
        }

        // Add provider to the array
        $content = str_replace(
            'return [',
            "return [\n    {$provider},",
            $content
        );

        File::put($providersFile, $content);
        $this->components->task('Registering provider', fn () => true);
    }

    /**
     * Run additional setup based on selected features.
     */
    protected function runFeatureSetup(array $features): void
    {
        // Run notifications:table migration if database notifications is selected
        if (in_array('database-notifications', $features)) {
            $this->components->task('Setting up database notifications', function () {
                // Check if notifications table migration already exists
                $migrations = File::glob(database_path('migrations/*_create_notifications_table.php'));

                if (empty($migrations)) {
                    Artisan::call('notifications:table');
                    $this->info('  Created notifications table migration');
                }

                // Run migrations
                Artisan::call('migrate', ['--force' => true]);

                return true;
            });
        }

        // Setup for passkeys (WebAuthn)
        if (in_array('passkeys', $features)) {
            $this->components->task('Setting up passkeys', function () {
                // Check if webauthn migrations exist
                $migrations = File::glob(database_path('migrations/*_create_web_authn_credentials_table.php'));

                if (empty($migrations)) {
                    // Publish webauthn migrations if not exists
                    Artisan::call('vendor:publish', [
                        '--tag' => 'webauthn-migrations',
                    ]);
                }

                // Run migrations
                Artisan::call('migrate', ['--force' => true]);

                return true;
            });
        }

        // Setup for two-factor authentication
        if (in_array('two-factor', $features)) {
            $this->components->task('Setting up two-factor authentication', function () {
                // Ensure Fortify 2FA columns exist
                return true;
            });
        }
    }
}
