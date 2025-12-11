<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'panel:install {--force : Overwrite existing files}';

    protected $description = 'Install Laravilt Panel stubs and setup application';

    public function handle(): int
    {
        $this->info('Installing Laravilt Panel...');

        // Publish middleware
        $this->publishMiddleware();

        // Publish layouts
        $this->publishLayouts();

        // Publish components
        $this->publishComponents();

        // Publish types
        $this->publishTypes();

        // Publish bootstrap files
        $this->publishBootstrap();

        // Publish route files
        $this->publishRoutes();

        $this->newLine();
        $this->info('✓ Laravilt Panel installed successfully!');
        $this->info('✓ Run: php artisan make:panel admin to create your first panel.');

        return self::SUCCESS;
    }

    protected function publishMiddleware(): void
    {
        $this->copyStub(
            __DIR__.'/../../stubs/Middleware/HandleInertiaRequests.stub',
            app_path('Http/Middleware/HandleInertiaRequests.php')
        );

        $this->copyStub(
            __DIR__.'/../../stubs/Middleware/HandleAppearance.stub',
            app_path('Http/Middleware/HandleAppearance.php')
        );

        $this->components->info('Middleware published');
    }

    protected function publishLayouts(): void
    {
        $layouts = [
            'AppLayout.vue',
            'AuthLayout.vue',
            'app/AppSidebarLayout.vue',
            'app/AppHeaderLayout.vue',
            'auth/AuthSimpleLayout.vue',
            'auth/AuthSplitLayout.vue',
            'auth/AuthCardLayout.vue',
            'settings/Layout.vue',
        ];

        foreach ($layouts as $layout) {
            $stubPath = __DIR__."/../../stubs/layouts/{$layout}.stub";
            $targetPath = resource_path("js/layouts/{$layout}");

            $this->copyStub($stubPath, $targetPath);
        }

        $this->components->info('Layouts published');
    }

    protected function publishBootstrap(): void
    {
        // Only overwrite if force flag is set or user confirms
        if ($this->option('force') || $this->confirm('Overwrite bootstrap/app.php?', false)) {
            $this->copyStub(
                __DIR__.'/../../stubs/bootstrap/app.stub',
                base_path('bootstrap/app.php')
            );
            $this->components->info('Bootstrap app.php published');
        } else {
            $this->components->warn('Skipped bootstrap/app.php (already exists)');
        }

        // Publish providers if doesn't exist
        if (! File::exists(base_path('bootstrap/providers.php')) || $this->option('force')) {
            $this->copyStub(
                __DIR__.'/../../stubs/bootstrap/providers.stub',
                base_path('bootstrap/providers.php')
            );
            $this->components->info('Bootstrap providers.php published');
        } else {
            $this->components->warn('Skipped bootstrap/providers.php (already exists)');
        }
    }

    protected function publishRoutes(): void
    {
        // Publish web routes if doesn't exist
        if (! File::exists(base_path('routes/web.php')) || $this->option('force')) {
            $this->copyStub(
                __DIR__.'/../../stubs/routes/web.stub',
                base_path('routes/web.php')
            );
            $this->components->info('Route web.php published');
        } else {
            $this->components->warn('Skipped routes/web.php (already exists)');
        }

        // Publish settings routes
        $this->copyStub(
            __DIR__.'/../../stubs/routes/settings.stub',
            base_path('routes/settings.php')
        );
        $this->components->info('Route settings.php published');
    }

    protected function publishComponents(): void
    {
        $components = [
            'AppSidebarHeader.vue',
            'NavUser.vue',
            'UserMenuContent.vue',
        ];

        foreach ($components as $component) {
            $stubPath = __DIR__."/../../stubs/components/{$component}.stub";
            $targetPath = resource_path("js/components/{$component}");

            if (File::exists($stubPath)) {
                if (! File::exists($targetPath) || $this->option('force')) {
                    $this->copyStub($stubPath, $targetPath);
                }
            }
        }

        $this->components->info('Components published');
    }

    protected function publishTypes(): void
    {
        $stubPath = __DIR__.'/../../stubs/types/index.d.ts.stub';
        $targetPath = resource_path('js/types/index.d.ts');

        if (File::exists($stubPath)) {
            if (! File::exists($targetPath) || $this->option('force')) {
                $this->copyStub($stubPath, $targetPath);
                $this->components->info('Types published');
            } else {
                $this->components->warn('Skipped types/index.d.ts (already exists)');
            }
        }
    }

    protected function copyStub(string $from, string $to): void
    {
        $dir = dirname($to);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $content = file_get_contents($from);
        file_put_contents($to, $content);
    }
}
