<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakePageCommand extends Command
{
    protected $signature = 'laravilt:page
                            {panel? : The panel for the page}
                            {name? : The name of the page}
                            {--type= : Page type (basic|form|table|dashboard)}';

    protected $description = 'Create a new panel page with interactive options';

    protected string $pageType = 'basic';

    protected array $selectedFeatures = [];

    /**
     * Available page features.
     */
    protected array $availableFeatures = [
        'header-actions' => 'Header actions',
        'footer-actions' => 'Footer actions',
        'sidebar' => 'Custom sidebar',
        'breadcrumbs' => 'Custom breadcrumbs',
        'polling' => 'Auto-refresh polling',
        'widgets' => 'Dashboard widgets',
    ];

    public function handle(): int
    {
        // Get available panels
        $panels = $this->getAvailablePanels();

        if (empty($panels)) {
            $this->components->error('No panels found. Please create a panel first using: php artisan laravilt:panel');

            return self::FAILURE;
        }

        // Get panel
        $panel = $this->argument('panel') ?? select(
            label: 'Which panel is this page for?',
            options: array_combine($panels, $panels),
            default: $panels[0]
        );

        $panel = Str::studly($panel);

        // Get page name
        $name = $this->argument('name') ?? text(
            label: 'What is the page name?',
            placeholder: 'Settings',
            required: true,
            hint: 'Use StudlyCase (e.g., Settings, Reports, Analytics)'
        );

        $name = Str::studly($name);

        // Get page type
        $this->pageType = $this->option('type') ?? select(
            label: 'What type of page?',
            options: [
                'basic' => 'Basic Page - Custom content',
                'form' => 'Form Page - Settings or input form',
                'table' => 'Table Page - Data listing',
                'dashboard' => 'Dashboard Page - Widgets overview',
            ],
            default: 'basic'
        );

        // Get page features
        $this->selectedFeatures = multiselect(
            label: 'Which features would you like to enable?',
            options: $this->availableFeatures,
            default: [],
            required: false,
            hint: 'Use space to select, enter to confirm'
        );

        // Create PHP page class
        $this->createPageClass($panel, $name);

        // Create Vue view file
        $this->createVueViewFile($panel, $name);

        $this->newLine();
        $this->components->info("Page [{$name}] created successfully for panel [{$panel}]!");
        $this->newLine();

        $slug = Str::kebab($name);
        $this->components->bulletList([
            "PHP Class: app/Laravilt/{$panel}/Pages/{$name}.php",
            "Vue View: resources/js/pages/{$panel}/{$name}.vue",
            "URL: /{$slug}",
        ]);

        // Clear caches (don't rebuild as closures can't be serialized)
        $this->newLine();
        $this->call('optimize:clear');

        return self::SUCCESS;
    }

    /**
     * Get available panels.
     */
    protected function getAvailablePanels(): array
    {
        $laraviltPath = app_path('Laravilt');

        if (! File::isDirectory($laraviltPath)) {
            return [];
        }

        return collect(File::directories($laraviltPath))
            ->map(fn ($dir) => basename($dir))
            ->values()
            ->toArray();
    }

    /**
     * Create the page class.
     */
    protected function createPageClass(string $panel, string $name): void
    {
        $path = app_path("Laravilt/{$panel}/Pages/{$name}.php");

        File::ensureDirectoryExists(dirname($path));

        $content = $this->generatePageClass($panel, $name);
        File::put($path, $content);

        $this->components->task('Creating page class', fn () => true);
    }

    /**
     * Generate page class content.
     */
    protected function generatePageClass(string $panel, string $name): string
    {
        $slug = Str::kebab($name);
        $title = Str::title(Str::snake($name, ' '));

        $imports = $this->buildPageImports();
        $traits = $this->buildPageTraits();
        $properties = $this->buildPageProperties($slug, $title);
        $methods = $this->buildPageMethods();

        $baseClass = match ($this->pageType) {
            'form' => 'SettingsPage',
            'table' => 'ListRecords',
            'dashboard' => 'Dashboard',
            default => 'Page',
        };

        return <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Pages;

{$imports}use Laravilt\Panel\Pages\\{$baseClass};

class {$name} extends {$baseClass}
{{$traits}{$properties}{$methods}
}
PHP;
    }

    /**
     * Build page imports.
     */
    protected function buildPageImports(): string
    {
        $imports = [];

        if (in_array('header-actions', $this->selectedFeatures) || in_array('footer-actions', $this->selectedFeatures)) {
            $imports[] = 'use Laravilt\Actions\Action;';
        }

        if (in_array('widgets', $this->selectedFeatures)) {
            $imports[] = '// Import your widgets here';
        }

        if (empty($imports)) {
            return '';
        }

        return implode("\n", $imports)."\n";
    }

    /**
     * Build page traits.
     */
    protected function buildPageTraits(): string
    {
        $traits = [];

        if (in_array('polling', $this->selectedFeatures)) {
            $traits[] = "    protected static ?string \$pollingInterval = '10s';";
        }

        if (empty($traits)) {
            return '';
        }

        return "\n".implode("\n", $traits);
    }

    /**
     * Build page properties.
     */
    protected function buildPageProperties(string $slug, string $title): string
    {
        $properties = [
            "    protected static ?string \$navigationIcon = 'File';",
            "    protected static ?string \$navigationLabel = '{$title}';",
            "    protected static ?string \$slug = '{$slug}';",
        ];

        if ($this->pageType === 'dashboard') {
            $properties[] = '    protected static ?int $navigationSort = -1;';
        }

        return "\n".implode("\n\n", $properties);
    }

    /**
     * Build page methods.
     */
    protected function buildPageMethods(): string
    {
        $methods = [];

        if (in_array('header-actions', $this->selectedFeatures)) {
            $methods[] = <<<'PHP'

    public function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action(fn () => $this->save()),
        ];
    }
PHP;
        }

        if (in_array('footer-actions', $this->selectedFeatures)) {
            $methods[] = <<<'PHP'

    public function getFooterActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel')
                ->color('secondary'),
        ];
    }
PHP;
        }

        if (in_array('widgets', $this->selectedFeatures)) {
            $methods[] = <<<'PHP'

    public function getWidgets(): array
    {
        return [
            // Add your widgets here
        ];
    }
PHP;
        }

        if (in_array('breadcrumbs', $this->selectedFeatures)) {
            $methods[] = <<<'PHP'

    public function getBreadcrumbs(): array
    {
        return [
            '/' => 'Home',
            '#' => $this->getTitle(),
        ];
    }
PHP;
        }

        return implode("\n", $methods);
    }

    /**
     * Create the Vue view file.
     */
    protected function createVueViewFile(string $panel, string $name): void
    {
        $viewPath = resource_path("js/pages/{$panel}");
        $viewFile = "{$viewPath}/{$name}.vue";

        if (File::exists($viewFile)) {
            $this->components->warn("Vue view already exists: {$viewFile}");

            return;
        }

        File::ensureDirectoryExists($viewPath);

        $content = $this->generateVueView($name);
        File::put($viewFile, $content);

        $this->components->task('Creating Vue view', fn () => true);
    }

    /**
     * Generate Vue view content.
     */
    protected function generateVueView(string $name): string
    {
        $title = Str::title(Str::snake($name, ' '));

        return match ($this->pageType) {
            'dashboard' => $this->generateDashboardView($name, $title),
            'form' => $this->generateFormView($name, $title),
            'table' => $this->generateTableView($name, $title),
            default => $this->generateBasicView($name, $title),
        };
    }

    /**
     * Generate basic page view.
     */
    protected function generateBasicView(string $name, string $title): string
    {
        return <<<VUE
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Heading } from '@/components';
</script>

<template>
    <AppLayout :title="'{$title}'">
        <div class="space-y-6">
            <Heading>{$title}</Heading>

            <div class="rounded-lg border p-6">
                <p class="text-muted-foreground">
                    This is the {$name} page. Add your content here.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
VUE;
    }

    /**
     * Generate dashboard view.
     */
    protected function generateDashboardView(string $name, string $title): string
    {
        return <<<VUE
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Heading } from '@/components';
</script>

<template>
    <AppLayout :title="'{$title}'">
        <div class="space-y-6">
            <Heading>{$title}</Heading>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Add widget components here -->
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
                <!-- Add chart components here -->
            </div>
        </div>
    </AppLayout>
</template>
VUE;
    }

    /**
     * Generate form view.
     */
    protected function generateFormView(string $name, string $title): string
    {
        return <<<VUE
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Heading } from '@/components';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :title="'{$title}'">
        <div class="space-y-6">
            <Heading>{$title}</Heading>

            <Card>
                <CardHeader>
                    <CardTitle>{$title}</CardTitle>
                    <CardDescription>
                        Configure your {$name} settings.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4">
                        <!-- Add form fields here -->

                        <div class="flex justify-end">
                            <Button type="submit">Save Changes</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
VUE;
    }

    /**
     * Generate table view.
     */
    protected function generateTableView(string $name, string $title): string
    {
        return <<<VUE
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Heading } from '@/components';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :title="'{$title}'">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading>{$title}</Heading>

                <div class="flex items-center gap-2">
                    <!-- Add action buttons here -->
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Records</CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Add table component here -->
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
VUE;
    }
}
