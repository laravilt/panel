<?php

namespace Laravilt\Panel\Concerns;

use Illuminate\Support\Facades\Route;

trait HasAuth
{
    /**
     * Login configuration.
     */
    protected ?array $loginConfig = null;

    /**
     * Register configuration.
     */
    protected ?array $registerConfig = null;

    /**
     * Password reset configuration.
     */
    protected ?array $passwordResetConfig = null;

    /**
     * Reset password configuration.
     */
    protected ?array $resetPasswordConfig = null;

    /**
     * Email verification configuration.
     */
    protected ?array $emailVerificationConfig = null;

    /**
     * OTP configuration.
     */
    protected ?array $otpConfig = null;

    /**
     * Profile configuration.
     */
    protected ?array $profileConfig = null;

    /**
     * Social login configuration.
     */
    protected ?array $socialLoginConfig = null;

    protected ?\Laravilt\Auth\Services\SocialProviderManager $socialProviderManager = null;

    /**
     * Two-factor provider manager.
     */
    protected ?\Laravilt\Auth\Services\TwoFactorProviderManager $twoFactorProviderManager = null;

    /**
     * Two-factor authentication configuration.
     */
    protected ?array $twoFactorConfig = null;

    /**
     * Session management configuration.
     */
    protected ?array $sessionManagementConfig = null;

    /**
     * API tokens configuration.
     */
    protected ?array $apiTokensConfig = null;

    /**
     * Passkeys configuration.
     */
    protected ?array $passkeysConfig = null;

    /**
     * Magic links configuration.
     */
    protected ?array $magicLinksConfig = null;

    /**
     * Connected accounts configuration.
     */
    protected ?array $connectedAccountsConfig = null;

    /**
     * Locale and timezone configuration.
     */
    protected ?array $localeTimezoneConfig = null;

    /**
     * Require password for social login users.
     */
    protected bool $requirePasswordForSocialLogin = true;

    /**
     * Require password for social login users.
     */
    public function requirePasswordForSocialLogin(bool $require = true): static
    {
        $this->requirePasswordForSocialLogin = $require;

        return $this;
    }

    /**
     * Check if password is required for social login users.
     */
    public function shouldRequirePasswordForSocialLogin(): bool
    {
        return $this->requirePasswordForSocialLogin;
    }

    /**
     * Configure login.
     */
    public function login(?string $page = null, ?string $path = null): static
    {
        $this->loginConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Login::class,
            'path' => $path ?? 'login',
        ];

        // Automatically add auth middleware when login is enabled
        $this->ensureAuthMiddleware();

        return $this;
    }

    /**
     * Disable login.
     */
    public function disableLogin(): static
    {
        $this->loginConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if login is enabled.
     */
    public function hasLogin(): bool
    {
        return $this->loginConfig['enabled'] ?? false;
    }

    /**
     * Get login page class.
     */
    public function getLoginPage(): ?string
    {
        return $this->loginConfig['page'] ?? \Laravilt\Auth\Pages\Login::class;
    }

    /**
     * Get login path.
     */
    public function getLoginPath(): string
    {
        return $this->loginConfig['path'] ?? 'login';
    }

    /**
     * Get the full login URL.
     */
    public function loginUrl(): string
    {
        return $this->url($this->getLoginPath());
    }

    /**
     * Configure registration.
     */
    public function registration(?string $page = null, ?string $path = null): static
    {
        $this->registerConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Register::class,
            'path' => $path ?? 'register',
        ];

        return $this;
    }

    /**
     * Disable registration.
     */
    public function disableRegistration(): static
    {
        $this->registerConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if registration is enabled.
     */
    public function hasRegistration(): bool
    {
        return $this->registerConfig['enabled'] ?? false;
    }

    /**
     * Get register page class.
     */
    public function getRegisterPage(): ?string
    {
        return $this->registerConfig['page'] ?? \Laravilt\Auth\Pages\Register::class;
    }

    /**
     * Get register path.
     */
    public function getRegisterPath(): string
    {
        return $this->registerConfig['path'] ?? 'register';
    }

    /**
     * Get the full register URL.
     */
    public function registerUrl(): string
    {
        return $this->url($this->getRegisterPath());
    }

    /**
     * Configure password reset.
     */
    public function passwordReset(?string $page = null, ?string $path = null): static
    {
        $this->passwordResetConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\ForgotPassword::class,
            'path' => $path ?? 'forgot-password',
        ];

        // Also enable reset password page by default
        $this->resetPassword();

        return $this;
    }

    /**
     * Disable password reset.
     */
    public function disablePasswordReset(): static
    {
        $this->passwordResetConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if password reset is enabled.
     */
    public function hasPasswordReset(): bool
    {
        return $this->passwordResetConfig['enabled'] ?? false;
    }

    /**
     * Get password reset page class.
     */
    public function getPasswordResetPage(): ?string
    {
        return $this->passwordResetConfig['page'] ?? null;
    }

    /**
     * Get password reset path.
     */
    public function getPasswordResetPath(): string
    {
        return $this->passwordResetConfig['path'] ?? 'forgot-password';
    }

    /**
     * Configure reset password.
     */
    public function resetPassword(?string $page = null, ?string $path = null): static
    {
        $this->resetPasswordConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\ResetPassword::class,
            'path' => $path ?? 'reset-password',
        ];

        return $this;
    }

    /**
     * Get reset password page class.
     */
    public function getResetPasswordPage(): ?string
    {
        return $this->resetPasswordConfig['page'] ?? \Laravilt\Auth\Pages\ResetPassword::class;
    }

    /**
     * Get reset password path.
     */
    public function getResetPasswordPath(): string
    {
        return $this->resetPasswordConfig['path'] ?? 'reset-password';
    }

    /**
     * Configure email verification.
     */
    public function emailVerification(?string $page = null, ?string $path = null): static
    {
        $this->emailVerificationConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\EmailVerification::class,
            'path' => $path ?? 'verify-email',
        ];

        return $this;
    }

    /**
     * Disable email verification.
     */
    public function disableEmailVerification(): static
    {
        $this->emailVerificationConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if email verification is enabled.
     */
    public function hasEmailVerification(): bool
    {
        return $this->emailVerificationConfig['enabled'] ?? false;
    }

    /**
     * Configure OTP authentication.
     */
    public function otp(?string $page = null, ?string $path = null): static
    {
        $this->otpConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\OTP::class,
            'path' => $path ?? 'otp',
        ];

        return $this;
    }

    /**
     * Disable OTP authentication.
     */
    public function disableOtp(): static
    {
        $this->otpConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if OTP is enabled.
     */
    public function hasOtp(): bool
    {
        return $this->otpConfig['enabled'] ?? false;
    }

    /**
     * Get OTP page class.
     */
    public function getOtpPage(): ?string
    {
        return $this->otpConfig['page'] ?? null;
    }

    /**
     * Get OTP path.
     */
    public function getOtpPath(): string
    {
        return $this->otpConfig['path'] ?? 'otp';
    }

    /**
     * Configure profile page.
     */
    public function profile(?string $page = null, ?string $path = null): static
    {
        $this->profileConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Profile::class,
            'path' => $path ?? 'profile',
        ];

        return $this;
    }

    /**
     * Disable profile page.
     */
    public function disableProfile(): static
    {
        $this->profileConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if profile is enabled.
     */
    public function hasProfile(): bool
    {
        return $this->profileConfig['enabled'] ?? false;
    }

    /**
     * Get profile page class.
     */
    public function getProfilePage(): ?string
    {
        return $this->profileConfig['page'] ?? null;
    }

    /**
     * Get profile path.
     */
    public function getProfilePath(): string
    {
        return $this->profileConfig['path'] ?? 'profile';
    }

    /**
     * Configure social login with builder or array.
     */
    public function socialLogin(\Closure|array $config): static
    {
        $builder = new \Laravilt\Auth\Builders\SocialProviderBuilder;

        if ($config instanceof \Closure) {
            // New builder pattern
            $config($builder);
        } else {
            // Legacy array pattern - auto-register default providers
            foreach ($config as $providerName) {
                $providerClass = match ($providerName) {
                    'google' => \Laravilt\Auth\Drivers\SocialProviders\GoogleProvider::class,
                    'github' => \Laravilt\Auth\Drivers\SocialProviders\GitHubProvider::class,
                    'facebook' => \Laravilt\Auth\Drivers\SocialProviders\FacebookProvider::class,
                    'twitter' => \Laravilt\Auth\Drivers\SocialProviders\TwitterProvider::class,
                    'linkedin' => \Laravilt\Auth\Drivers\SocialProviders\LinkedInProvider::class,
                    'discord' => \Laravilt\Auth\Drivers\SocialProviders\DiscordProvider::class,
                    'jira' => \Laravilt\Auth\Drivers\SocialProviders\JiraProvider::class,
                    default => null,
                };

                if ($providerClass) {
                    $builder->provider($providerClass);
                }
            }
        }

        $this->socialProviderManager = new \Laravilt\Auth\Services\SocialProviderManager($this->getId());
        $this->socialProviderManager->setBuilder($builder);

        $this->socialLoginConfig = [
            'enabled' => true,
        ];

        return $this;
    }

    /**
     * Disable social login.
     */
    public function disableSocialLogin(): static
    {
        $this->socialLoginConfig = ['enabled' => false];
        $this->socialProviderManager = null;

        return $this;
    }

    /**
     * Check if social login is enabled.
     */
    public function hasSocialLogin(): bool
    {
        return $this->socialLoginConfig['enabled'] ?? false;
    }

    /**
     * Get social provider manager.
     */
    public function getSocialProviderManager(): ?\Laravilt\Auth\Services\SocialProviderManager
    {
        return $this->socialProviderManager;
    }

    /**
     * Get social login providers (for backwards compatibility).
     */
    public function getSocialProviders(): array
    {
        if (! $this->socialProviderManager) {
            return [];
        }

        return $this->socialProviderManager->getProvidersForFrontend();
    }

    /**
     * Get enabled social provider objects.
     */
    public function getEnabledSocialProviders(): array
    {
        if (! $this->socialProviderManager) {
            return [];
        }

        return $this->socialProviderManager->getEnabledProviders();
    }

    /**
     * Configure two-factor authentication.
     */
    public function twoFactor(?string $page = null, ?string $path = null, ?callable $builder = null): static
    {
        // Initialize the provider manager
        $this->twoFactorProviderManager = new \Laravilt\Auth\Services\TwoFactorProviderManager($this->getId());

        // Initialize builder
        $builderInstance = new \Laravilt\Auth\Builders\TwoFactorProviderBuilder;

        // If no custom builder provided, register default providers
        if ($builder === null) {
            $builderInstance
                ->provider(\Laravilt\Auth\Drivers\TotpDriver::class)
                ->provider(\Laravilt\Auth\Drivers\EmailDriver::class);
        } else {
            // Call the builder callback
            $builder($builderInstance);
        }

        // Register all providers from builder
        foreach ($builderInstance->getProviders() as $driver) {
            $this->twoFactorProviderManager->register($driver);
        }

        $this->twoFactorConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Profile\ManageTwoFactor::class,
            'path' => $path ?? 'profile/two-factor',
        ];

        return $this;
    }

    /**
     * Disable two-factor authentication.
     */
    public function disableTwoFactor(): static
    {
        $this->twoFactorConfig = ['enabled' => false];
        $this->twoFactorProviderManager = null;

        return $this;
    }

    /**
     * Check if two-factor authentication is enabled.
     */
    public function hasTwoFactor(): bool
    {
        return $this->twoFactorConfig['enabled'] ?? false;
    }

    /**
     * Get two-factor provider manager.
     */
    public function getTwoFactorProviderManager(): ?\Laravilt\Auth\Services\TwoFactorProviderManager
    {
        return $this->twoFactorProviderManager;
    }

    /**
     * Get two-factor providers (for frontend).
     */
    public function getTwoFactorProviders(): array
    {
        if (! $this->twoFactorProviderManager) {
            return [];
        }

        return $this->twoFactorProviderManager->getDriversForFrontend();
    }

    /**
     * Get two-factor page class.
     */
    public function getTwoFactorPage(): ?string
    {
        return $this->twoFactorConfig['page'] ?? null;
    }

    /**
     * Get two-factor path.
     */
    public function getTwoFactorPath(): string
    {
        return $this->twoFactorConfig['path'] ?? 'profile/two-factor';
    }

    /**
     * Configure session management.
     */
    public function sessionManagement(?string $page = null, ?string $path = null): static
    {
        $this->sessionManagementConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Profile\ManageSessions::class,
            'path' => $path ?? 'profile/sessions',
        ];

        return $this;
    }

    /**
     * Disable session management.
     */
    public function disableSessionManagement(): static
    {
        $this->sessionManagementConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if session management is enabled.
     */
    public function hasSessionManagement(): bool
    {
        return $this->sessionManagementConfig['enabled'] ?? false;
    }

    /**
     * Get session management page class.
     */
    public function getSessionManagementPage(): ?string
    {
        return $this->sessionManagementConfig['page'] ?? null;
    }

    /**
     * Get session management path.
     */
    public function getSessionManagementPath(): string
    {
        return $this->sessionManagementConfig['path'] ?? 'profile/sessions';
    }

    /**
     * Configure API tokens.
     */
    public function apiTokens(?string $page = null, ?string $path = null): static
    {
        $this->apiTokensConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Profile\ManageApiTokens::class,
            'path' => $path ?? 'profile/api-tokens',
        ];

        return $this;
    }

    /**
     * Disable API tokens.
     */
    public function disableApiTokens(): static
    {
        $this->apiTokensConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if API tokens is enabled.
     */
    public function hasApiTokens(): bool
    {
        return $this->apiTokensConfig['enabled'] ?? false;
    }

    /**
     * Get API tokens page class.
     */
    public function getApiTokensPage(): ?string
    {
        return $this->apiTokensConfig['page'] ?? null;
    }

    /**
     * Get API tokens path.
     */
    public function getApiTokensPath(): string
    {
        return $this->apiTokensConfig['path'] ?? 'profile/api-tokens';
    }

    /**
     * Configure passkeys.
     */
    public function passkeys(?string $page = null, ?string $path = null): static
    {
        $this->passkeysConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Profile\ManagePasskeys::class,
            'path' => $path ?? 'profile/passkeys',
        ];

        return $this;
    }

    /**
     * Disable passkeys.
     */
    public function disablePasskeys(): static
    {
        $this->passkeysConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if passkeys is enabled.
     */
    public function hasPasskeys(): bool
    {
        return $this->passkeysConfig['enabled'] ?? false;
    }

    /**
     * Get passkeys page class.
     */
    public function getPasskeysPage(): ?string
    {
        return $this->passkeysConfig['page'] ?? null;
    }

    /**
     * Get passkeys path.
     */
    public function getPasskeysPath(): string
    {
        return $this->passkeysConfig['path'] ?? 'profile/passkeys';
    }

    /**
     * Configure magic links.
     */
    public function magicLinks(?string $page = null, ?string $path = null): static
    {
        $this->magicLinksConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\MagicLink::class,
            'path' => $path ?? 'magic-link',
        ];

        return $this;
    }

    /**
     * Disable magic links.
     */
    public function disableMagicLinks(): static
    {
        $this->magicLinksConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if magic links is enabled.
     */
    public function hasMagicLinks(): bool
    {
        return $this->magicLinksConfig['enabled'] ?? false;
    }

    /**
     * Get magic links page class.
     */
    public function getMagicLinksPage(): ?string
    {
        return $this->magicLinksConfig['page'] ?? null;
    }

    /**
     * Get magic links path.
     */
    public function getMagicLinksPath(): string
    {
        return $this->magicLinksConfig['path'] ?? 'magic-link';
    }

    /**
     * Configure connected accounts.
     */
    public function connectedAccounts(?string $page = null, ?string $path = null): static
    {
        $this->connectedAccountsConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\Profile\ConnectedAccounts::class,
            'path' => $path ?? 'profile/connected-accounts',
        ];

        return $this;
    }

    /**
     * Disable connected accounts.
     */
    public function disableConnectedAccounts(): static
    {
        $this->connectedAccountsConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if connected accounts is enabled.
     */
    public function hasConnectedAccounts(): bool
    {
        return $this->connectedAccountsConfig['enabled'] ?? false;
    }

    /**
     * Get connected accounts page class.
     */
    public function getConnectedAccountsPage(): ?string
    {
        return $this->connectedAccountsConfig['page'] ?? null;
    }

    /**
     * Get connected accounts path.
     */
    public function getConnectedAccountsPath(): string
    {
        return $this->connectedAccountsConfig['path'] ?? 'profile/connected-accounts';
    }

    /**
     * Configure locale and timezone settings.
     */
    public function localeTimezone(?string $page = null, ?string $path = null): static
    {
        $this->localeTimezoneConfig = [
            'enabled' => true,
            'page' => $page ?? \Laravilt\Auth\Pages\LocaleTimezone::class,
            'path' => $path ?? 'settings/locale-timezone',
        ];

        return $this;
    }

    /**
     * Disable locale and timezone settings.
     */
    public function disableLocaleTimezone(): static
    {
        $this->localeTimezoneConfig = ['enabled' => false];

        return $this;
    }

    /**
     * Check if locale and timezone settings is enabled.
     */
    public function hasLocaleTimezone(): bool
    {
        return $this->localeTimezoneConfig['enabled'] ?? false;
    }

    /**
     * Get locale and timezone page class.
     */
    public function getLocaleTimezonePage(): ?string
    {
        return $this->localeTimezoneConfig['page'] ?? null;
    }

    /**
     * Get locale and timezone path.
     */
    public function getLocaleTimezonePath(): string
    {
        return $this->localeTimezoneConfig['path'] ?? 'settings/locale-timezone';
    }

    /**
     * Build auth user menu items.
     */
    public function buildAuthUserMenu(\Laravilt\Panel\Navigation\UserMenu $menu): void
    {
        // Add Settings link if any profile features are enabled
        if ($this->hasProfile() || $this->hasTwoFactor() || $this->hasSessionManagement() ||
            $this->hasApiTokens() || $this->hasPasskeys() || $this->hasConnectedAccounts()) {

            $menu->item(\Laravilt\Panel\Navigation\NavigationItem::make(__('laravilt-panel::panel.user_menu.settings'))
                ->translationKey('laravilt-panel::panel.user_menu.settings')
                ->icon('cog-6-tooth')
                ->url($this->url('settings/profile')));
        }

        // Add Logout - use panel's logout route if login is enabled, otherwise fallback to Laravel's logout route
        $logoutUrl = $this->hasLogin()
            ? route($this->getId().'.logout')
            : (Route::has('logout') ? route('logout') : '/logout');

        $menu->item(\Laravilt\Panel\Navigation\NavigationItem::make(__('laravilt-panel::panel.user_menu.logout'))
            ->translationKey('laravilt-panel::panel.user_menu.logout')
            ->icon('arrow-right-on-rectangle')
            ->url($logoutUrl)
            ->method('post'));
    }

    /**
     * Get profile pages based on enabled features.
     */
    protected function getAuthPages(): array
    {
        $pages = [];

        if ($this->hasProfile() && class_exists(\Laravilt\Auth\Pages\Profile::class)) {
            $pages[] = \Laravilt\Auth\Pages\Profile::class;
        }

        if ($this->hasProfile() && class_exists(\Laravilt\Auth\Pages\Profile\ChangePassword::class)) {
            $pages[] = \Laravilt\Auth\Pages\Profile\ChangePassword::class;
        }

        if ($this->hasTwoFactor() && class_exists(\Laravilt\Auth\Pages\Profile\ManageTwoFactor::class)) {
            $pages[] = \Laravilt\Auth\Pages\Profile\ManageTwoFactor::class;
        }

        if ($this->hasSessionManagement() && class_exists(\Laravilt\Auth\Pages\Profile\ManageSessions::class)) {
            $pages[] = \Laravilt\Auth\Pages\Profile\ManageSessions::class;
        }

        if ($this->hasApiTokens() && class_exists(\Laravilt\Auth\Pages\Profile\ManageApiTokens::class)) {
            $pages[] = \Laravilt\Auth\Pages\Profile\ManageApiTokens::class;
        }

        if ($this->hasPasskeys() && class_exists(\Laravilt\Auth\Pages\Profile\ManagePasskeys::class)) {
            $pages[] = \Laravilt\Auth\Pages\Profile\ManagePasskeys::class;
        }

        if ($this->hasConnectedAccounts() && class_exists(\Laravilt\Auth\Pages\Profile\ConnectedAccounts::class)) {
            $pages[] = \Laravilt\Auth\Pages\Profile\ConnectedAccounts::class;
        }

        if ($this->hasLocaleTimezone() && class_exists(\Laravilt\Auth\Pages\LocaleTimezone::class)) {
            $pages[] = \Laravilt\Auth\Pages\LocaleTimezone::class;
        }

        // Add Settings cluster if not already registered
        if (count($pages) > 0 && class_exists(\Laravilt\Auth\Clusters\Settings::class)) {
            array_unshift($pages, \Laravilt\Auth\Clusters\Settings::class);
        }

        return $pages;
    }

    /**
     * Register auth routes for this panel.
     */
    public function registerAuthRoutes(): void
    {
        Route::middleware([
            'web',
            \Laravilt\Panel\Middleware\IdentifyPanel::class.':'.$this->getId(),
            \Laravilt\Panel\Http\Middleware\HandleLocalization::class,
            \Laravilt\Panel\Http\Middleware\SharePanelData::class,
        ])
            ->prefix($this->getPath())
            ->group(function () {
                // Login routes
                if ($this->hasLogin() && $loginPage = $this->getLoginPage()) {
                    Route::get($this->getLoginPath(), [$loginPage, 'create'])
                        ->middleware('guest')
                        ->name($this->getId().'.login');

                    Route::post($this->getLoginPath(), [$loginPage, 'store'])
                        ->name($this->getId().'.login.store');
                }

                // Registration routes
                if ($this->hasRegistration() && $registerPage = $this->getRegisterPage()) {
                    Route::get($this->getRegisterPath(), [$registerPage, 'create'])
                        ->middleware('guest')
                        ->name($this->getId().'.register');

                    Route::post($this->getRegisterPath(), [$registerPage, 'store'])
                        ->name($this->getId().'.register.store');
                }

                // Password reset routes
                if ($this->hasPasswordReset()) {
                    if ($passwordResetPage = $this->getPasswordResetPage()) {
                        Route::get($this->getPasswordResetPath(), [$passwordResetPage, 'create'])
                            ->name($this->getId().'.password.request');

                        Route::post($this->getPasswordResetPath(), [$passwordResetPage, 'store'])
                            ->name($this->getId().'.password.email');
                    }

                    if ($resetPasswordPage = $this->getResetPasswordPage()) {
                        Route::get($this->getResetPasswordPath().'/{token}', [$resetPasswordPage, 'create'])
                            ->name($this->getId().'.password.reset');

                        Route::post($this->getResetPasswordPath(), [$resetPasswordPage, 'store'])
                            ->name($this->getId().'.password.update');
                    }
                }

                // Email verification routes
                if ($this->hasEmailVerification() && $emailVerificationPage = $this->getEmailVerificationConfig()['page'] ?? null) {
                    Route::get($this->getEmailVerificationConfig()['path'] ?? 'verify-email', [$emailVerificationPage, 'create'])
                        ->name($this->getId().'.verification.notice');

                    Route::post('email/verification-notification', [$emailVerificationPage, 'store'])
                        ->middleware(['auth', 'throttle:6,1'])
                        ->name($this->getId().'.verification.send');
                }

                // OTP routes
                if ($this->hasOtp() && $otpPage = $this->getOtpPage()) {
                    Route::get($this->getOtpPath(), [$otpPage, 'create'])
                        ->name($this->getId().'.otp.login');

                    Route::post($this->getOtpPath(), [$otpPage, 'store'])
                        ->name($this->getId().'.otp.verify');

                    Route::post('otp/resend', [$otpPage, 'resend'])
                        ->middleware(['throttle:6,1'])
                        ->name($this->getId().'.otp.resend');
                }

                // Magic Links routes
                if ($this->hasMagicLinks() && $magicLinksPage = $this->getMagicLinksPage()) {
                    Route::get($this->getMagicLinksPath(), [$magicLinksPage, 'create'])
                        ->name($this->getId().'.magic-link.create');

                    Route::post($this->getMagicLinksPath(), [$magicLinksPage, 'store'])
                        ->name($this->getId().'.magic-link.store');

                    Route::get('magic-link/verify/{token}', [$magicLinksPage, 'verify'])
                        ->middleware(['signed'])
                        ->name('laravilt.auth.magic-link.verify');
                }

                // Social Login routes
                if ($this->hasSocialLogin() && class_exists(\Laravilt\Auth\Http\Controllers\Auth\SocialAuthController::class)) {
                    Route::get('auth/{provider}/redirect', [\Laravilt\Auth\Http\Controllers\Auth\SocialAuthController::class, 'redirect'])
                        ->name($this->getId().'.auth.social.redirect');

                    Route::get('auth/{provider}/callback', [\Laravilt\Auth\Http\Controllers\Auth\SocialAuthController::class, 'callback'])
                        ->name($this->getId().'.auth.social.callback');
                }
            });

        // Set Password routes (authenticated but nullable password)
        Route::middleware(array_merge(
            ['web'],
            ['auth'.($this->getAuthGuard() ? ':'.$this->getAuthGuard() : '')],
            [\Laravilt\Panel\Middleware\IdentifyPanel::class.':'.$this->getId()],
            [\Laravilt\Panel\Http\Middleware\HandleLocalization::class]
        ))
            ->prefix($this->getPath())
            ->group(function () {
                if (class_exists(\Laravilt\Auth\Pages\SetPassword::class)) {
                    Route::get('set-password', [\Laravilt\Auth\Pages\SetPassword::class, 'create'])
                        ->name($this->getId().'.auth.set-password');
                }
            });

        // Two-Factor Authentication challenge routes (guest or mid-authentication)
        Route::middleware(array_merge(
            ['web'],
            [\Laravilt\Panel\Middleware\IdentifyPanel::class.':'.$this->getId()],
            [\Laravilt\Panel\Http\Middleware\HandleLocalization::class],
            [\Laravilt\Panel\Http\Middleware\SharePanelData::class]
        ))
            ->prefix($this->getPath())
            ->group(function () {
                if (class_exists(\Laravilt\Auth\Pages\Auth\TwoFactorChallenge::class)) {
                    Route::get('two-factor/challenge', [\Laravilt\Auth\Pages\Auth\TwoFactorChallenge::class, 'create'])
                        ->name($this->getId().'.two-factor.challenge');

                    Route::post('two-factor/challenge', [\Laravilt\Auth\Pages\Auth\TwoFactorChallenge::class, 'store'])
                        ->name($this->getId().'.two-factor.challenge.verify');

                    Route::post('two-factor/resend', [\Laravilt\Auth\Pages\Auth\TwoFactorChallenge::class, 'resend'])
                        ->middleware(['throttle:3,1'])
                        ->name($this->getId().'.two-factor.resend');
                }

                if (class_exists(\Laravilt\Auth\Pages\Auth\TwoFactorRecovery::class)) {
                    Route::get('two-factor/recovery', [\Laravilt\Auth\Pages\Auth\TwoFactorRecovery::class, 'create'])
                        ->name($this->getId().'.two-factor.recovery');

                    Route::post('two-factor/recovery', [\Laravilt\Auth\Pages\Auth\TwoFactorRecovery::class, 'store'])
                        ->name($this->getId().'.two-factor.recovery.verify');
                }

                // Passkey login routes (for 2FA alternative)
                Route::get('passkey/login-options', [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'loginOptions'])
                    ->name($this->getId().'.passkey.login-options');

                Route::post('passkey/login', [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'login'])
                    ->name($this->getId().'.passkey.login');

                // Magic link routes (for 2FA alternative)
                Route::post('magic-link/send', [\Laravilt\Auth\Http\Controllers\MagicLinkController::class, 'send'])
                    ->middleware(['throttle:3,1'])
                    ->name($this->getId().'.magic-link.send');

                Route::get('magic-link/verify/{token}', [\Laravilt\Auth\Http\Controllers\MagicLinkController::class, 'verify'])
                    ->middleware(['signed'])
                    ->name($this->getId().'.magic-link.verify');
            });

        // Authenticated routes
        // IMPORTANT: IdentifyPanel must come BEFORE 'panel.auth' so that redirectTo() knows the current panel
        $authenticatedMiddleware = array_merge(
            ['web'],
            [\Laravilt\Panel\Middleware\IdentifyPanel::class.':'.$this->getId()],
            ['panel.auth'],
            [\Laravilt\Panel\Http\Middleware\HandleLocalization::class],
            [\Laravilt\Panel\Http\Middleware\SharePanelData::class]
        );

        // Only add RequirePassword middleware if social login is enabled and requires password
        if ($this->hasSocialLogin() && $this->shouldRequirePasswordForSocialLogin()) {
            $authenticatedMiddleware[] = \Laravilt\Auth\Http\Middleware\RequirePassword::class;
        }

        // Only add RequireTwoFactorAuthentication middleware if 2FA is enabled for this panel
        if ($this->hasTwoFactor()) {
            $authenticatedMiddleware[] = \Laravilt\Auth\Http\Middleware\RequireTwoFactorAuthentication::class;
        }

        Route::middleware($authenticatedMiddleware)
            ->prefix($this->getPath())
            ->group(function () {
                // Logout route
                if ($this->hasLogin() && $loginPage = $this->getLoginPage()) {
                    Route::post('logout', [$loginPage, 'destroy'])
                        ->name($this->getId().'.logout');
                }

                // Quick locale update route
                Route::post('locale', [\Laravilt\Panel\Http\Controllers\LocaleController::class, 'update'])
                    ->name($this->getId().'.locale.update');

                // Email verification verify route
                if ($this->hasEmailVerification() && $emailVerificationPage = $this->getEmailVerificationConfig()['page'] ?? null) {
                    Route::get('email/verify/{id}/{hash}', [$emailVerificationPage, 'verify'])
                        ->middleware(['signed', 'throttle:6,1'])
                        ->name($this->getId().'.verification.verify');
                }

                // Profile routes - Check if profile page belongs to a cluster
                if ($this->hasProfile() && $profilePage = $this->getProfilePage()) {
                    $clusterClass = $profilePage::getCluster();

                    if ($clusterClass) {
                        // Register cluster routes
                        $this->registerClusterRoutes($clusterClass);

                        // Add redirect from old profile path to cluster path
                        $clusterSlug = $clusterClass::getSlug();
                        $pageSlug = $profilePage::getSlug();
                        Route::get($this->getProfilePath(), function () use ($clusterSlug, $pageSlug) {
                            $panel = app(\Laravilt\Panel\PanelRegistry::class)->getCurrent();

                            return redirect($panel->url("{$clusterSlug}/{$pageSlug}"));
                        })->name($this->getId().'.profile');
                    } else {
                        // Legacy non-cluster routes
                        Route::get($this->getProfilePath(), [$profilePage, 'edit'])
                            ->name($this->getId().'.profile.edit');

                        Route::patch($this->getProfilePath(), [$profilePage, 'update'])
                            ->name($this->getId().'.profile.update');

                        Route::put('password', [$profilePage, 'updatePassword'])
                            ->name($this->getId().'.profile.password.update');

                        Route::delete('profile', [$profilePage, 'destroy'])
                            ->name($this->getId().'.profile.destroy');
                    }
                }

                // Two-Factor Authentication routes (only if not using cluster)
                if ($this->hasTwoFactor()) {
                    $twoFactorPage = $this->getTwoFactorPage();

                    // Only register legacy routes if two-factor page is NOT in a cluster
                    if (! $twoFactorPage::getCluster()) {
                        Route::get($this->getTwoFactorPath().'/status', [\Laravilt\Auth\Http\Controllers\TwoFactorAuthController::class, 'status'])
                            ->name($this->getId().'.two-factor.status');

                        Route::post($this->getTwoFactorPath().'/enable', [$twoFactorPage, 'enable'])
                            ->name($this->getId().'.two-factor.enable');

                        Route::post($this->getTwoFactorPath().'/confirm', [$twoFactorPage, 'confirm'])
                            ->name($this->getId().'.two-factor.confirm');

                        Route::delete($this->getTwoFactorPath().'/disable', [$twoFactorPage, 'disable'])
                            ->name($this->getId().'.two-factor.disable');

                        Route::post($this->getTwoFactorPath().'/recovery-codes', [$twoFactorPage, 'regenerateRecoveryCodes'])
                            ->name($this->getId().'.two-factor.recovery-codes');
                    }
                }

                // Session Management routes (only if not using cluster)
                if ($this->hasSessionManagement()) {
                    $sessionsPage = $this->getSessionManagementPage();

                    if (! $sessionsPage::getCluster()) {
                        Route::get($this->getSessionManagementPath(), [$sessionsPage, 'index'])
                            ->name($this->getId().'.sessions.index');

                        Route::delete($this->getSessionManagementPath().'/{sessionId}', [$sessionsPage, 'destroy'])
                            ->name($this->getId().'.sessions.destroy');

                        Route::delete($this->getSessionManagementPath().'/others', [$sessionsPage, 'destroyOthers'])
                            ->name($this->getId().'.sessions.destroy-others');
                    }
                }

                // API Tokens routes (only if not using cluster)
                if ($this->hasApiTokens()) {
                    $apiTokensPage = $this->getApiTokensPage();

                    if (! $apiTokensPage::getCluster()) {
                        Route::get($this->getApiTokensPath(), [$apiTokensPage, 'index'])
                            ->name($this->getId().'.api-tokens.index');

                        Route::post($this->getApiTokensPath(), [$apiTokensPage, 'store'])
                            ->name($this->getId().'.api-tokens.store');

                        Route::put($this->getApiTokensPath().'/{tokenId}', [$apiTokensPage, 'update'])
                            ->name($this->getId().'.api-tokens.update');

                        Route::delete($this->getApiTokensPath().'/{tokenId}', [$apiTokensPage, 'destroy'])
                            ->name($this->getId().'.api-tokens.destroy');
                    }
                }

                // Passkeys routes (only if not using cluster)
                if ($this->hasPasskeys() && $passkeysPage = $this->getPasskeysPage()) {
                    if (! $passkeysPage::getCluster()) {
                        Route::get($this->getPasskeysPath().'/register-options', [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'registerOptions'])
                            ->name($this->getId().'.passkeys.register-options');

                        Route::post($this->getPasskeysPath().'/register', [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'register'])
                            ->name($this->getId().'.passkeys.register');

                        Route::delete($this->getPasskeysPath().'/{credentialId}', [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'destroy'])
                            ->name($this->getId().'.passkeys.destroy');
                    }
                }

                // Connected Accounts routes (only if not using cluster)
                if ($this->hasConnectedAccounts() && $connectedAccountsPage = $this->getConnectedAccountsPage()) {
                    if (! $connectedAccountsPage::getCluster()) {
                        Route::get($this->getConnectedAccountsPath(), [$connectedAccountsPage, 'index'])
                            ->name($this->getId().'.connected-accounts.index');

                        Route::delete($this->getConnectedAccountsPath().'/{provider}', [$connectedAccountsPage, 'destroy'])
                            ->name($this->getId().'.connected-accounts.destroy');
                    }
                }
            });
    }

    /**
     * Get email verification config.
     */
    protected function getEmailVerificationConfig(): array
    {
        return $this->emailVerificationConfig ?? [];
    }

    /**
     * Ensure auth middleware is added to the panel.
     */
    protected function ensureAuthMiddleware(): void
    {
        $currentMiddleware = $this->getMiddleware();

        // Check if 'auth' middleware is already present
        $hasAuthMiddleware = collect($currentMiddleware)->contains(function ($middleware) {
            return is_string($middleware) && str_starts_with($middleware, 'auth');
        });

        if (! $hasAuthMiddleware) {
            // Get the auth guard
            $guard = $this->getAuthGuard();

            // Use panel's custom auth middleware or default
            $authMiddleware = \Laravilt\Panel\Http\Middleware\Authenticate::class;

            if ($guard) {
                $authMiddleware .= ":{$guard}";
            }

            $this->middleware([...$currentMiddleware, $authMiddleware]);
        }
    }

    /**
     * Ensure RequirePassword middleware is added/removed based on configuration.
     */
    protected function ensureRequirePasswordMiddleware(): void
    {
        $currentMiddleware = $this->getMiddleware();
        $requirePasswordClass = \Laravilt\Auth\Http\Middleware\RequirePassword::class;

        // Check if RequirePassword middleware is present
        $hasRequirePassword = collect($currentMiddleware)->contains($requirePasswordClass);

        if ($this->shouldRequirePasswordForSocialLogin() && $this->hasSocialLogin()) {
            // Should have the middleware
            if (! $hasRequirePassword) {
                $this->middleware([...$currentMiddleware, $requirePasswordClass]);
            }
        } else {
            // Should not have the middleware
            if ($hasRequirePassword) {
                $filtered = collect($currentMiddleware)
                    ->reject(fn ($m) => $m === $requirePasswordClass)
                    ->values()
                    ->toArray();
                $this->middleware($filtered);
            }
        }
    }

    /**
     * Configure Laravel's default auth routes for this panel.
     */
    public function configureAuthDefaults(): void
    {
        if ($this->hasLogin()) {
            // Override the default 'login' route name
            config([
                'auth.defaults.guard' => $this->getAuthGuard() ?? 'web',
            ]);
        }
    }

    /**
     * Register routes for a cluster and its pages.
     */
    protected function registerClusterRoutes(string $clusterClass): void
    {
        $clusterSlug = $clusterClass::getSlug();

        // Find all pages that belong to this cluster
        $clusterPages = collect($this->getPages())
            ->filter(fn ($pageClass) => method_exists($pageClass, 'getCluster') && $pageClass::getCluster() === $clusterClass)
            ->values();

        // Register routes for each page in the cluster
        foreach ($clusterPages as $pageClass) {
            $pageSlug = $pageClass::getSlug();
            $pagePath = "{$clusterSlug}/{$pageSlug}";

            // Determine which methods exist on the page
            $reflection = new \ReflectionClass($pageClass);

            // GET route (index, create, or edit)
            if ($reflection->hasMethod('index')) {
                \Illuminate\Support\Facades\Route::get($pagePath, [$pageClass, 'index'])
                    ->name($this->getId().".{$clusterSlug}.{$pageSlug}.index");
            } elseif ($reflection->hasMethod('edit')) {
                \Illuminate\Support\Facades\Route::get($pagePath, [$pageClass, 'edit'])
                    ->name($this->getId().".{$clusterSlug}.{$pageSlug}.edit");
            } elseif ($reflection->hasMethod('create')) {
                \Illuminate\Support\Facades\Route::get($pagePath, [$pageClass, 'create'])
                    ->name($this->getId().".{$clusterSlug}.{$pageSlug}");
            }

            // POST route (store)
            if ($reflection->hasMethod('store')) {
                \Illuminate\Support\Facades\Route::post($pagePath, [$pageClass, 'store'])
                    ->name($this->getId().".{$clusterSlug}.{$pageSlug}.store");
            }

            // PATCH/PUT routes (update)
            if ($reflection->hasMethod('update')) {
                \Illuminate\Support\Facades\Route::patch($pagePath, [$pageClass, 'update'])
                    ->name($this->getId().".{$clusterSlug}.{$pageSlug}.update");

                \Illuminate\Support\Facades\Route::put($pagePath, [$pageClass, 'update']);
            }

            // DELETE route (destroy)
            if ($reflection->hasMethod('destroy')) {
                \Illuminate\Support\Facades\Route::delete($pagePath, [$pageClass, 'destroy'])
                    ->name($this->getId().".{$clusterSlug}.{$pageSlug}.destroy");
            }
        }

        // Also register the Two-Factor routes under the cluster if ManageTwoFactor is in the cluster
        if ($this->hasTwoFactor()) {
            $twoFactorPage = \Laravilt\Auth\Pages\Profile\ManageTwoFactor::class;
            if (in_array($twoFactorPage, $clusterPages->toArray())) {
                $twoFactorSlug = $twoFactorPage::getSlug();
                $twoFactorPath = "{$clusterSlug}/{$twoFactorSlug}";

                \Illuminate\Support\Facades\Route::post("{$twoFactorPath}/enable", [$twoFactorPage, 'enable'])
                    ->name($this->getId().'.two-factor.enable');

                \Illuminate\Support\Facades\Route::post("{$twoFactorPath}/confirm", [$twoFactorPage, 'confirm'])
                    ->name($this->getId().'.two-factor.confirm');

                \Illuminate\Support\Facades\Route::post("{$twoFactorPath}/cancel", [$twoFactorPage, 'cancel'])
                    ->name($this->getId().'.two-factor.cancel');

                \Illuminate\Support\Facades\Route::delete("{$twoFactorPath}/disable", [$twoFactorPage, 'disable'])
                    ->name($this->getId().'.two-factor.disable');

                \Illuminate\Support\Facades\Route::post("{$twoFactorPath}/recovery-codes", [$twoFactorPage, 'regenerateRecoveryCodes'])
                    ->name($this->getId().'.two-factor.recovery-codes');
            }
        }

        // Also register the API Token routes under the cluster if ManageApiTokens is in the cluster
        if ($this->hasApiTokens()) {
            $apiTokensPage = \Laravilt\Auth\Pages\Profile\ManageApiTokens::class;
            if (in_array($apiTokensPage, $clusterPages->toArray())) {
                $apiTokensSlug = $apiTokensPage::getSlug();
                $apiTokensPath = "{$clusterSlug}/{$apiTokensSlug}";

                \Illuminate\Support\Facades\Route::post("{$apiTokensPath}/store", [\Laravilt\Auth\Http\Controllers\ApiTokenController::class, 'store'])
                    ->name($this->getId().'.api-tokens.store');

                \Illuminate\Support\Facades\Route::delete("{$apiTokensPath}/{token}", [\Laravilt\Auth\Http\Controllers\ApiTokenController::class, 'destroy'])
                    ->name($this->getId().'.api-tokens.destroy');

                \Illuminate\Support\Facades\Route::post("{$apiTokensPath}/revoke-all", [\Laravilt\Auth\Http\Controllers\ApiTokenController::class, 'revokeAll'])
                    ->name($this->getId().'.api-tokens.revoke-all');
            }
        }

        // Also register the Passkeys routes under the cluster if ManagePasskeys is in the cluster
        if ($this->hasPasskeys()) {
            $passkeysPage = \Laravilt\Auth\Pages\Profile\ManagePasskeys::class;
            if (in_array($passkeysPage, $clusterPages->toArray())) {
                $passkeysSlug = $passkeysPage::getSlug();
                $passkeysPath = "{$clusterSlug}/{$passkeysSlug}";

                \Illuminate\Support\Facades\Route::get("{$passkeysPath}/register-options", [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'registerOptions'])
                    ->name($this->getId().'.passkeys.register-options');

                \Illuminate\Support\Facades\Route::post("{$passkeysPath}/register", [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'register'])
                    ->name($this->getId().'.passkeys.register');

                \Illuminate\Support\Facades\Route::delete("{$passkeysPath}/{credentialId}", [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'destroy'])
                    ->name($this->getId().'.passkeys.destroy');
            }
        }
    }
}
