<?php

/*
|--------------------------------------------------------------------------
| Laravilt React app-shell stubs
|--------------------------------------------------------------------------
|
| Consumed by `php artisan laravilt:install` (StubManifestPublisher) when the
| React stack is chosen, on top of the Laravel React starter kit.
|
| publish: stub path relative to this directory => target relative to the app base path
|          (directories are copied recursively)
| delete:  app-relative paths removed before publishing
|
*/

return [
    'publish' => [
        // Tooling
        'package.json.stub' => 'package.json',
        'vite.config.ts.stub' => 'vite.config.ts',
        'tsconfig.json.stub' => 'tsconfig.json',

        // Entry points
        'app.tsx.stub' => 'resources/js/app.tsx',
        'views/app.blade.php.stub' => 'resources/views/app.blade.php',
        'css/app.css.stub' => 'resources/css/app.css',

        // Routes (React page names)
        'routes/web.php.stub' => 'routes/web.php',
        'routes/settings.php.stub' => 'routes/settings.php',

        // Components
        'components/alert-error.tsx.stub' => 'resources/js/components/alert-error.tsx',
        'components/app-content.tsx.stub' => 'resources/js/components/app-content.tsx',
        'components/app-header.tsx.stub' => 'resources/js/components/app-header.tsx',
        'components/app-logo.tsx.stub' => 'resources/js/components/app-logo.tsx',
        'components/app-logo-icon.tsx.stub' => 'resources/js/components/app-logo-icon.tsx',
        'components/app-shell.tsx.stub' => 'resources/js/components/app-shell.tsx',
        'components/app-sidebar.tsx.stub' => 'resources/js/components/app-sidebar.tsx',
        'components/app-sidebar-header.tsx.stub' => 'resources/js/components/app-sidebar-header.tsx',
        'components/appearance-tabs.tsx.stub' => 'resources/js/components/appearance-tabs.tsx',
        'components/breadcrumbs.tsx.stub' => 'resources/js/components/breadcrumbs.tsx',
        'components/heading.tsx.stub' => 'resources/js/components/heading.tsx',
        'components/heading-small.tsx.stub' => 'resources/js/components/heading-small.tsx',
        'components/icon.tsx.stub' => 'resources/js/components/icon.tsx',
        'components/input-error.tsx.stub' => 'resources/js/components/input-error.tsx',
        'components/nav-footer.tsx.stub' => 'resources/js/components/nav-footer.tsx',
        'components/nav-main.tsx.stub' => 'resources/js/components/nav-main.tsx',
        'components/nav-user.tsx.stub' => 'resources/js/components/nav-user.tsx',
        'components/text-link.tsx.stub' => 'resources/js/components/text-link.tsx',
        'components/two-factor-recovery-codes.tsx.stub' => 'resources/js/components/two-factor-recovery-codes.tsx',
        'components/two-factor-setup-modal.tsx.stub' => 'resources/js/components/two-factor-setup-modal.tsx',
        'components/user-info.tsx.stub' => 'resources/js/components/user-info.tsx',
        'components/user-menu-content.tsx.stub' => 'resources/js/components/user-menu-content.tsx',

        // shadcn/ui primitives (new-york, Radix) and utilities
        'ui' => 'resources/js/components/ui',
        'lib/utils.ts' => 'resources/js/lib/utils.ts',

        // Hooks
        'hooks/use-appearance.tsx.stub' => 'resources/js/hooks/use-appearance.tsx',
        'hooks/use-initials.tsx.stub' => 'resources/js/hooks/use-initials.tsx',
        'hooks/use-localization.ts.stub' => 'resources/js/hooks/use-localization.ts',
        'hooks/use-panel-font.ts.stub' => 'resources/js/hooks/use-panel-font.ts',
        'hooks/use-two-factor-auth.ts.stub' => 'resources/js/hooks/use-two-factor-auth.ts',

        // Layouts
        'layouts/app-layout.tsx.stub' => 'resources/js/layouts/app-layout.tsx',
        'layouts/auth-layout.tsx.stub' => 'resources/js/layouts/auth-layout.tsx',
        'layouts/app/app-header-layout.tsx.stub' => 'resources/js/layouts/app/app-header-layout.tsx',
        'layouts/app/app-sidebar-layout.tsx.stub' => 'resources/js/layouts/app/app-sidebar-layout.tsx',
        'layouts/auth/auth-card-layout.tsx.stub' => 'resources/js/layouts/auth/auth-card-layout.tsx',
        'layouts/auth/auth-simple-layout.tsx.stub' => 'resources/js/layouts/auth/auth-simple-layout.tsx',
        'layouts/auth/auth-split-layout.tsx.stub' => 'resources/js/layouts/auth/auth-split-layout.tsx',

        // Types
        'types/auth.ts.stub' => 'resources/js/types/auth.ts',
        'types/global.d.ts.stub' => 'resources/js/types/global.d.ts',
        'types/index.ts.stub' => 'resources/js/types/index.ts',
        'types/navigation.ts.stub' => 'resources/js/types/navigation.ts',
        'types/panel.ts.stub' => 'resources/js/types/panel.ts',
        'types/ui.ts.stub' => 'resources/js/types/ui.ts',

        // Pages
        'pages/welcome.tsx.stub' => 'resources/js/pages/welcome.tsx',
    ],

    'delete' => [
        // Starter-kit pages: auth and settings are provided by laravilt/auth, panels ship their own dashboard
        'resources/js/pages/auth',
        'resources/js/pages/settings',
        'resources/js/pages/dashboard.tsx',

        // Only served the removed settings pages; imports @/routes/{profile,security,appearance},
        // which Wayfinder stops generating once routes/settings.php is no longer loaded
        'resources/js/layouts/settings',

        // Imports @/actions/App/Http/Controllers/Settings/ProfileController (no longer routed)
        'resources/js/components/delete-user.tsx',
    ],
];
