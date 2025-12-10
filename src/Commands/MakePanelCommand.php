<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakePanelCommand extends Command
{
    protected $signature = 'laravilt:panel {id} {--path=}';

    protected $description = 'Create a new panel';

    public function handle(): int
    {
        $id = $this->argument('id');
        $path = $this->option('path') ?? $id;
        $studlyId = Str::studly($id);

        // Create provider
        $this->createPanelProvider($studlyId, $id, $path);

        // Create directory structure
        $this->createDirectories($studlyId);

        // Create dashboard page
        $this->createDashboard($studlyId);

        // Register provider
        $this->registerProvider($studlyId);

        $this->newLine();
        $this->components->info("Panel [{$id}] created successfully!");
        $this->components->info("Panel provider: app/Providers/Laravilt/{$studlyId}PanelProvider.php");
        $this->components->info("Pages directory: app/Laravilt/{$studlyId}/Pages");
        $this->components->info("Resources directory: app/Laravilt/{$studlyId}/Resources");
        $this->components->info("Widgets directory: app/Laravilt/{$studlyId}/Widgets");

        return self::SUCCESS;
    }

    protected function createPanelProvider(string $studlyId, string $id, string $path): void
    {
        $stub = File::get(__DIR__.'/../../stubs/panel-provider.stub');

        $content = str_replace(
            ['{{ studlyId }}', '{{ id }}', '{{ path }}'],
            [$studlyId, $id, $path],
            $stub
        );

        $providerPath = app_path("Providers/Laravilt/{$studlyId}PanelProvider.php");

        File::ensureDirectoryExists(dirname($providerPath));
        File::put($providerPath, $content);

        $this->components->info("Provider created: {$providerPath}");
    }

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

        $this->components->info("Directories created in app/Laravilt/{$studlyId}");
    }

    protected function createDashboard(string $studlyId): void
    {
        // Create PHP Dashboard page
        $stub = File::get(__DIR__.'/../../stubs/dashboard-page.stub');
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ title }}'],
            ["App\\Laravilt\\{$studlyId}\\Pages", 'Dashboard', "{$studlyId} Dashboard"],
            $stub
        );
        $path = app_path("Laravilt/{$studlyId}/Pages/Dashboard.php");
        File::put($path, $content);
        $this->components->info("Dashboard page created: {$path}");

        // Create Vue Dashboard page
        $vueStub = File::get(__DIR__.'/../../stubs/dashboard.stub');
        $vueContent = str_replace('{{ studlyId }}', $studlyId, $vueStub);
        $vuePath = resource_path("js/pages/{$studlyId}/Dashboard.vue");
        File::ensureDirectoryExists(dirname($vuePath));
        File::put($vuePath, $vueContent);
        $this->components->info("Dashboard view created: {$vuePath}");
    }

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
            $this->components->warn('Provider already registered in bootstrap/providers.php');

            return;
        }

        // Add provider to the array
        $content = str_replace(
            'return [',
            "return [\n    {$provider},",
            $content
        );

        File::put($providersFile, $content);
        $this->components->info('Provider registered in bootstrap/providers.php');
    }
}
