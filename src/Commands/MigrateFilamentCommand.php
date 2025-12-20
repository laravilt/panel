<?php

declare(strict_types=1);

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laravilt\Panel\Commands\Concerns\ExtractComponents;
use Laravilt\Panel\Commands\Concerns\GeneratePages;
use Laravilt\Panel\Commands\Concerns\ScanComponents;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class MigrateFilamentCommand extends Command
{
    use ExtractComponents;
    use GeneratePages;
    use ScanComponents;

    protected $signature = 'laravilt:filament
                            {--source=app/Filament : Source directory containing Filament resources}
                            {--target=app/Laravilt : Target directory for Laravilt resources}
                            {--panel=Admin : Panel name to use}
                            {--dry-run : Show what would be changed without making changes}
                            {--force : Overwrite existing files}
                            {--all : Migrate all resources without selection}';

    protected $description = 'Migrate Filament PHP v3/v4 resources to Laravilt resources';

    protected Filesystem $files;

    protected array $stats = [
        'resources' => 0,
        'nested_resources' => 0,
        'forms' => 0,
        'tables' => 0,
        'infolists' => 0,
        'pages' => 0,
        'widgets' => 0,
        'relation_managers' => 0,
        'skipped' => 0,
    ];

    /**
     * Component types that can be migrated.
     */
    protected array $componentTypes = [
        'resources' => 'Resources',
        'pages' => 'Pages (standalone)',
        'widgets' => 'Widgets',
    ];

    /**
     * Namespace mappings from Filament to Laravilt.
     */
    protected array $namespaceMap = [
        // Core
        'Filament\\Resources\\Resource' => 'Laravilt\\Panel\\Resources\\Resource',
        'Filament\\Schemas\\Schema' => 'Laravilt\\Schemas\\Schema',
        'Filament\\Tables\\Table' => 'Laravilt\\Tables\\Table',

        // Third-party packages to Laravilt equivalents
        'RVxLab\\FilamentColorPicker\\Forms\\ColorPicker' => 'Laravilt\\Forms\\Components\\ColorPicker',
        'RVxLab\\FilamentColorPicker\\Columns\\ColorSwatch' => 'Laravilt\\Tables\\Columns\\ColorColumn',
        'RVxLab\\FilamentColorPicker\\Enum\\PopupPosition' => '',
        'FilamentTiptapEditor\\TiptapEditor' => 'Laravilt\\Forms\\Components\\RichEditor',
        'Mohamedsabil83\\FilamentFormsTinyeditor\\Components\\TinyEditor' => 'Laravilt\\Forms\\Components\\RichEditor',
        'Filament\\Forms\\Components\\SpatieMediaLibraryFileUpload' => 'Laravilt\\Forms\\Components\\FileUpload',

        // Forms - Component class mappings (specific first)
        'Filament\\Forms\\Components\\BelongsToSelect' => 'Laravilt\\Forms\\Components\\Select',
        'Filament\\Forms\\Components\\BelongsToManyMultiSelect' => 'Laravilt\\Forms\\Components\\Select',
        'Filament\\Forms\\Components\\Card' => 'Laravilt\\Schemas\\Components\\Section',
        'Filament\\Forms\\Components\\Grid' => 'Laravilt\\Schemas\\Components\\Grid',
        'Filament\\Forms\\Components\\Tabs' => 'Laravilt\\Schemas\\Components\\Tabs',
        'Filament\\Forms\\Components\\Tabs\\Tab' => 'Laravilt\\Schemas\\Components\\Tabs\\Tab',
        'Filament\\Forms\\Components\\Wizard' => 'Laravilt\\Schemas\\Components\\Wizard',
        'Filament\\Forms\\Components\\Wizard\\Step' => 'Laravilt\\Schemas\\Components\\Wizard\\Step',
        'Filament\\Forms\\Components\\Section' => 'Laravilt\\Schemas\\Components\\Section',
        'Filament\\Forms\\Components\\Fieldset' => 'Laravilt\\Schemas\\Components\\Fieldset',
        'Filament\\Forms\\Components\\' => 'Laravilt\\Forms\\Components\\',
        'Filament\\Forms\\Form' => 'Laravilt\\Schemas\\Schema',
        'Filament\\Forms' => 'Laravilt\\Forms',
        'Filament\\Resources\\Form' => 'Laravilt\\Schemas\\Schema',

        // Tables - Column class mappings (specific first)
        'Filament\\Tables\\Columns\\BadgeColumn' => 'Laravilt\\Tables\\Columns\\BadgeColumn',
        'Filament\\Tables\\Columns\\BooleanColumn' => 'Laravilt\\Tables\\Columns\\BooleanColumn',
        'Filament\\Tables\\Columns\\ColorColumn' => 'Laravilt\\Tables\\Columns\\ColorColumn',
        'Filament\\Tables\\Columns\\' => 'Laravilt\\Tables\\Columns\\',
        'Filament\\Tables\\Filters\\' => 'Laravilt\\Tables\\Filters\\',
        'Filament\\Tables\\Enums\\' => 'Laravilt\\Tables\\Enums\\',
        'Filament\\Tables\\Grouping\\' => 'Laravilt\\Tables\\Grouping\\',
        'Filament\\Tables' => 'Laravilt\\Tables',
        'Filament\\Resources\\Table' => 'Laravilt\\Tables\\Table',

        // Notifications
        'Filament\\Notifications\\Notification' => 'Laravilt\\Notifications\\Notification',
        'Filament\\Notifications\\' => 'Laravilt\\Notifications\\',

        // Schemas (Filament v4 uses Schemas namespace)
        // Utilities - must come before catch-all patterns
        'Filament\\Schemas\\Components\\Utilities\\Get' => 'Laravilt\\Support\\Utilities\\Get',
        'Filament\\Schemas\\Components\\Utilities\\Set' => 'Laravilt\\Support\\Utilities\\Set',
        'Filament\\Support\\Get' => 'Laravilt\\Support\\Utilities\\Get',
        'Filament\\Support\\Set' => 'Laravilt\\Support\\Utilities\\Set',
        'Filament\\Forms\\Get' => 'Laravilt\\Support\\Utilities\\Get',
        'Filament\\Forms\\Set' => 'Laravilt\\Support\\Utilities\\Set',
        'Filament\\Schemas\\Components\\Section' => 'Laravilt\\Schemas\\Components\\Section',
        'Filament\\Schemas\\Components\\Grid' => 'Laravilt\\Schemas\\Components\\Grid',
        'Filament\\Schemas\\Components\\Tabs' => 'Laravilt\\Schemas\\Components\\Tabs',
        'Filament\\Schemas\\Components\\Tabs\\Tab' => 'Laravilt\\Schemas\\Components\\Tabs\\Tab',
        'Filament\\Schemas\\Components\\Wizard' => 'Laravilt\\Schemas\\Components\\Wizard',
        'Filament\\Schemas\\Components\\Wizard\\Step' => 'Laravilt\\Schemas\\Components\\Wizard\\Step',
        'Filament\\Schemas\\Components\\Fieldset' => 'Laravilt\\Schemas\\Components\\Fieldset',
        'Filament\\Schemas\\Components\\' => 'Laravilt\\Schemas\\Components\\',
        'Filament\\Schemas\\Schema' => 'Laravilt\\Schemas\\Schema',
        'Filament\\Schemas' => 'Laravilt\\Schemas',

        // Infolists - Components become Entries
        'Filament\\Infolists\\Components\\TextEntry' => 'Laravilt\\Infolists\\Entries\\TextEntry',
        'Filament\\Infolists\\Components\\IconEntry' => 'Laravilt\\Infolists\\Entries\\IconEntry',
        'Filament\\Infolists\\Components\\ImageEntry' => 'Laravilt\\Infolists\\Entries\\ImageEntry',
        'Filament\\Infolists\\Components\\ColorEntry' => 'Laravilt\\Infolists\\Entries\\ColorEntry',
        'Filament\\Infolists\\Components\\BooleanEntry' => 'Laravilt\\Infolists\\Entries\\BooleanEntry',
        'Filament\\Infolists\\Components\\' => 'Laravilt\\Infolists\\Entries\\',
        'Filament\\Infolists\\Infolist' => 'Laravilt\\Infolists\\Infolist',
        'Filament\\Infolists\\Components' => 'Laravilt\\Infolists\\Entries',
        'Filament\\Infolists' => 'Laravilt\\Infolists',

        // Actions
        'Filament\\Actions\\Action' => 'Laravilt\\Actions\\Action',
        'Filament\\Actions\\EditAction' => 'Laravilt\\Actions\\EditAction',
        'Filament\\Actions\\DeleteAction' => 'Laravilt\\Actions\\DeleteAction',
        'Filament\\Actions\\ViewAction' => 'Laravilt\\Actions\\ViewAction',
        'Filament\\Actions\\CreateAction' => 'Laravilt\\Actions\\CreateAction',
        'Filament\\Actions\\BulkAction' => 'Laravilt\\Actions\\BulkAction',
        'Filament\\Actions\\' => 'Laravilt\\Actions\\',
        'Filament\\Actions' => 'Laravilt\\Actions',

        // Pages (Laravilt uses Laravilt\Panel\Pages, not Laravilt\Panel\Resources\Pages)
        'Filament\\Resources\\Pages\\ListRecords' => 'Laravilt\\Panel\\Pages\\ListRecords',
        'Filament\\Resources\\Pages\\CreateRecord' => 'Laravilt\\Panel\\Pages\\CreateRecord',
        'Filament\\Resources\\Pages\\EditRecord' => 'Laravilt\\Panel\\Pages\\EditRecord',
        'Filament\\Resources\\Pages\\ViewRecord' => 'Laravilt\\Panel\\Pages\\ViewRecord',
        'Filament\\Resources\\Pages\\ManageRecords' => 'Laravilt\\Panel\\Pages\\ManageRecords',
        'Filament\\Resources\\Pages\\ManageRelatedRecords' => 'Laravilt\\Panel\\Pages\\ManageRelatedRecords',
        'Filament\\Resources\\Pages\\Page' => 'Laravilt\\Panel\\Pages\\Page',

        // RelationManagers
        'Filament\\Resources\\RelationManagers\\RelationManager' => 'Laravilt\\Panel\\Resources\\RelationManagers\\RelationManager',

        // Forms Contracts and Concerns
        'Filament\\Forms\\Contracts\\HasForms' => 'Laravilt\\Forms\\Contracts\\HasForms',
        'Filament\\Forms\\Concerns\\InteractsWithForms' => 'Laravilt\\Forms\\Concerns\\InteractsWithForms',

        // Tables Contracts and Concerns
        'Filament\\Tables\\Contracts\\HasTable' => 'Laravilt\\Tables\\Contracts\\HasTable',
        'Filament\\Tables\\Concerns\\InteractsWithTable' => 'Laravilt\\Tables\\Concerns\\InteractsWithTable',

        // Actions Contracts and Concerns
        'Filament\\Actions\\Contracts\\HasActions' => 'Laravilt\\Actions\\Contracts\\HasActions',
        'Filament\\Actions\\Concerns\\InteractsWithActions' => 'Laravilt\\Actions\\Concerns\\InteractsWithActions',

        // NestedResources - Filament's nested resources extend Resource
        // In Laravilt, they extend NestedResource
        'Filament\\Resources\\Resource' => 'Laravilt\\Panel\\Resources\\Resource',

        // Standalone Pages
        'Filament\\Pages\\Page' => 'Laravilt\\Panel\\Pages\\Page',
        'Filament\\Pages\\Dashboard' => 'Laravilt\\Panel\\Pages\\Dashboard',
        'Filament\\Pages\\' => 'Laravilt\\Panel\\Pages\\',

        // Widgets
        'Filament\\Widgets\\Widget' => 'Laravilt\\Widgets\\Widget',
        'Filament\\Widgets\\StatsOverviewWidget' => 'Laravilt\\Widgets\\StatsOverviewWidget',
        'Filament\\Widgets\\StatsOverviewWidget\\Stat' => 'Laravilt\\Widgets\\Stat',
        'Filament\\Widgets\\ChartWidget' => 'Laravilt\\Widgets\\ChartWidget',
        'Filament\\Widgets\\TableWidget' => 'Laravilt\\Widgets\\TableWidget',
        'Filament\\Widgets\\' => 'Laravilt\\Widgets\\',
    ];

    /**
     * Icon mappings from Heroicon enum to Laravilt icon strings.
     */
    protected array $iconMap = [
        'Heroicon::OutlinedRectangleStack' => 'layers',
        'Heroicon::OutlinedUsers' => 'users',
        'Heroicon::OutlinedUser' => 'user',
        'Heroicon::OutlinedHome' => 'home',
        'Heroicon::OutlinedCog' => 'settings',
        'Heroicon::OutlinedCog6Tooth' => 'settings',
        'Heroicon::OutlinedDocument' => 'file',
        'Heroicon::OutlinedDocumentText' => 'file-text',
        'Heroicon::OutlinedFolder' => 'folder',
        'Heroicon::OutlinedTag' => 'tag',
        'Heroicon::OutlinedShoppingCart' => 'shopping-cart',
        'Heroicon::OutlinedCreditCard' => 'credit-card',
        'Heroicon::OutlinedChartBar' => 'bar-chart',
        'Heroicon::OutlinedCalendar' => 'calendar',
        'Heroicon::OutlinedBell' => 'bell',
        'Heroicon::OutlinedMail' => 'mail',
        'Heroicon::OutlinedInbox' => 'inbox',
        'Heroicon::OutlinedChat' => 'message-circle',
        'Heroicon::OutlinedHeart' => 'heart',
        'Heroicon::OutlinedStar' => 'star',
        'Heroicon::OutlinedBookmark' => 'bookmark',
        'Heroicon::OutlinedCheck' => 'check',
        'Heroicon::OutlinedX' => 'x',
        'Heroicon::OutlinedPlus' => 'plus',
        'Heroicon::OutlinedMinus' => 'minus',
        'Heroicon::OutlinedPencil' => 'edit',
        'Heroicon::OutlinedTrash' => 'trash',
        'Heroicon::OutlinedEye' => 'eye',
        'Heroicon::OutlinedLink' => 'link',
        'Heroicon::OutlinedGlobe' => 'globe',
        'Heroicon::OutlinedMap' => 'map',
        'Heroicon::OutlinedPhone' => 'phone',
        'Heroicon::OutlinedPhotograph' => 'image',
        'Heroicon::OutlinedCamera' => 'camera',
        'Heroicon::OutlinedVideoCamera' => 'video',
        'Heroicon::OutlinedMicrophone' => 'mic',
        'Heroicon::OutlinedSpeakerphone' => 'megaphone',
        'Heroicon::OutlinedClipboard' => 'clipboard',
        'Heroicon::OutlinedClipboardCheck' => 'clipboard-check',
        'Heroicon::OutlinedClipboardList' => 'clipboard-list',
        'Heroicon::OutlinedArchive' => 'archive',
        'Heroicon::OutlinedCloud' => 'cloud',
        'Heroicon::OutlinedDownload' => 'download',
        'Heroicon::OutlinedUpload' => 'upload',
        'Heroicon::OutlinedRefresh' => 'refresh-cw',
        'Heroicon::OutlinedLockClosed' => 'lock',
        'Heroicon::OutlinedLockOpen' => 'unlock',
        'Heroicon::OutlinedKey' => 'key',
        'Heroicon::OutlinedShield' => 'shield',
        'Heroicon::OutlinedShieldCheck' => 'shield-check',
        'Heroicon::OutlinedFire' => 'flame',
        'Heroicon::OutlinedLightningBolt' => 'zap',
        'Heroicon::OutlinedSparkles' => 'sparkles',
        'Heroicon::OutlinedCube' => 'box',
        'Heroicon::OutlinedPuzzle' => 'puzzle',
        'Heroicon::OutlinedTemplate' => 'layout',
        'Heroicon::OutlinedAdjustments' => 'sliders',
        'Heroicon::OutlinedFilter' => 'filter',
        'Heroicon::OutlinedSearch' => 'search',
        'Heroicon::OutlinedZoomIn' => 'zoom-in',
        'Heroicon::OutlinedZoomOut' => 'zoom-out',
    ];

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $source = base_path($this->option('source'));
        $target = base_path($this->option('target'));
        $panel = $this->option('panel');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (! $this->files->isDirectory($source)) {
            $this->error("Source directory does not exist: {$source}");

            return self::FAILURE;
        }

        $this->info('');
        $this->info('  <fg=cyan>╔══════════════════════════════════════════════════════════════╗</>');
        $this->info('  <fg=cyan>║</>       <fg=white;options=bold>Filament to Laravilt Migration Tool</>                     <fg=cyan>║</>');
        $this->info('  <fg=cyan>╚══════════════════════════════════════════════════════════════╝</>');
        $this->info('');

        $this->info("  Source: <fg=yellow>{$source}</>");
        $this->info("  Target: <fg=yellow>{$target}/{$panel}</>");
        $this->info("  Panel:  <fg=yellow>{$panel}</>");

        if ($dryRun) {
            $this->warn('');
            $this->warn('  Running in DRY-RUN mode. No files will be changed.');
        }

        $this->info('');
        $this->line('  ──────────────────────────────────────────────────────────────');
        $this->info('');

        // Scan all component types
        $allComponents = $this->scanAllComponents($source);

        // Check what's available
        $availableTypes = [];
        if (! empty($allComponents['resources'])) {
            $availableTypes['resources'] = 'Resources ('.count($allComponents['resources']).')';
        }
        if (! empty($allComponents['pages'])) {
            $availableTypes['pages'] = 'Pages ('.count($allComponents['pages']).')';
        }
        if (! empty($allComponents['widgets'])) {
            $availableTypes['widgets'] = 'Widgets ('.count($allComponents['widgets']).')';
        }

        if (empty($availableTypes)) {
            $this->warn('  No Filament components found in the source directory.');

            return self::SUCCESS;
        }

        // Show what was found
        $this->info('  <fg=green;options=bold>Found components:</>');
        foreach ($availableTypes as $type => $label) {
            $this->info("    • {$label}");
        }
        $this->info('');

        $migrateAll = $this->option('all');
        $selectedTypes = [];
        $selectedResources = [];
        $selectedPages = [];
        $selectedWidgets = [];

        if (! $migrateAll) {
            // Step 1: Select component types to migrate
            $selectedTypes = multiselect(
                label: 'Which component types would you like to migrate?',
                options: $availableTypes,
                default: array_keys($availableTypes),
                hint: 'Use space to select, enter to confirm',
                required: true,
            );

            $this->info('');

            // Step 2: For each selected type, ask for specific items
            if (in_array('resources', $selectedTypes) && ! empty($allComponents['resources'])) {
                $selectedResources = $this->selectItems(
                    'resources',
                    $allComponents['resources'],
                    'Select resources to migrate'
                );
            }

            if (in_array('pages', $selectedTypes) && ! empty($allComponents['pages'])) {
                $selectedPages = $this->selectItems(
                    'pages',
                    $allComponents['pages'],
                    'Select pages to migrate'
                );
            }

            if (in_array('widgets', $selectedTypes) && ! empty($allComponents['widgets'])) {
                $selectedWidgets = $this->selectItems(
                    'widgets',
                    $allComponents['widgets'],
                    'Select widgets to migrate'
                );
            }
        } else {
            $selectedTypes = array_keys($availableTypes);
            $selectedResources = array_keys($allComponents['resources'] ?? []);
            $selectedPages = array_keys($allComponents['pages'] ?? []);
            $selectedWidgets = array_keys($allComponents['widgets'] ?? []);
        }

        // Display selection summary
        $this->info('');
        $this->info('  <fg=cyan;options=bold>Migration Selection:</>');
        if (! empty($selectedResources)) {
            $this->info('    Resources: <fg=yellow>'.implode(', ', $selectedResources).'</>');
        }
        if (! empty($selectedPages)) {
            $this->info('    Pages: <fg=yellow>'.implode(', ', $selectedPages).'</>');
        }
        if (! empty($selectedWidgets)) {
            $this->info('    Widgets: <fg=yellow>'.implode(', ', $selectedWidgets).'</>');
        }
        $this->info('');

        // Filter components to migrate
        $resourcesToMigrate = array_filter(
            $allComponents['resources'] ?? [],
            fn ($r) => in_array($r['name'], $selectedResources)
        );
        $pagesToMigrate = array_filter(
            $allComponents['pages'] ?? [],
            fn ($p) => in_array($p['name'], $selectedPages)
        );
        $widgetsToMigrate = array_filter(
            $allComponents['widgets'] ?? [],
            fn ($w) => in_array($w['name'], $selectedWidgets)
        );

        // Also filter related files (pages, relation managers for resources)
        $filesToMigrate = [];
        foreach ($allComponents['other'] ?? [] as $file) {
            // Check if this file belongs to a selected resource
            foreach ($selectedResources as $resourceName) {
                if (Str::contains($file['relativePath'], $resourceName.'Resource/') ||
                    Str::contains($file['relativePath'], $resourceName.'/')) {
                    $filesToMigrate[] = $file;
                    break;
                }
            }
        }

        $totalFiles = count($resourcesToMigrate) + count($pagesToMigrate) + count($widgetsToMigrate) + count($filesToMigrate);

        if ($totalFiles === 0) {
            $this->warn('  No components selected for migration.');

            return self::SUCCESS;
        }

        $this->output->progressStart($totalFiles);

        // Process selected resources
        foreach ($resourcesToMigrate as $resource) {
            $content = $this->files->get($resource['path']);
            $this->processResourceWithExtraction($content, $resource['relativePath'], $resource['path'], $target, $panel, $resource['type'], $dryRun, $force);
            $this->output->progressAdvance();
        }

        // Process selected pages
        foreach ($pagesToMigrate as $page) {
            $this->processPage($page, $source, $target, $panel, $dryRun, $force);
            $this->output->progressAdvance();
        }

        // Process selected widgets
        foreach ($widgetsToMigrate as $widget) {
            $this->processWidget($widget, $source, $target, $panel, $dryRun, $force);
            $this->output->progressAdvance();
        }

        // Process related files (resource pages, relation managers)
        foreach ($filesToMigrate as $file) {
            $this->processFile($file['path'], $source, $target, $panel, $dryRun, $force);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // Show summary
        $this->info('');
        $this->line('  ──────────────────────────────────────────────────────────────');
        $this->info('');
        $this->info('  <fg=green;options=bold>Migration Summary</>');
        $this->info('');
        $this->info("    Resources:         <fg=cyan>{$this->stats['resources']}</>");
        $this->info("    Nested Resources:  <fg=cyan>{$this->stats['nested_resources']}</>");
        $this->info("    Forms:             <fg=cyan>{$this->stats['forms']}</>");
        $this->info("    Tables:            <fg=cyan>{$this->stats['tables']}</>");
        $this->info("    Infolists:         <fg=cyan>{$this->stats['infolists']}</>");
        $this->info("    Pages:             <fg=cyan>{$this->stats['pages']}</>");
        $this->info("    Widgets:           <fg=cyan>{$this->stats['widgets']}</>");
        $this->info("    RelationManagers:  <fg=cyan>{$this->stats['relation_managers']}</>");
        $this->info("    Skipped:           <fg=yellow>{$this->stats['skipped']}</>");
        $this->info('');

        $total = array_sum($this->stats) - $this->stats['skipped'];
        if ($dryRun) {
            $this->info("  <fg=yellow>{$total}</> files would be migrated.");
        } else {
            $this->info("  <fg=green>{$total}</> files successfully migrated.");
        }
        $this->info('');

        return self::SUCCESS;
    }

    /**
     * Scan all component types from source directory.
     */
    protected function scanAllComponents(string $source): array
    {
        $components = [
            'resources' => [],
            'pages' => [],
            'widgets' => [],
            'other' => [],
        ];

        // Scan Resources directory
        $resourcesDir = $source.DIRECTORY_SEPARATOR.'Resources';
        if ($this->files->isDirectory($resourcesDir)) {
            $finder = new Finder;
            $finder->files()->name('*.php')->in($resourcesDir);

            foreach ($finder as $file) {
                $content = $this->files->get($file->getPathname());
                $relativePath = 'Resources'.DIRECTORY_SEPARATOR.Str::after($file->getPathname(), $resourcesDir.DIRECTORY_SEPARATOR);
                $fileType = $this->detectFileType($content, $relativePath);

                if ($fileType === 'resource' || $fileType === 'nested_resource') {
                    $resourceName = basename($relativePath, '.php');
                    $resourceName = preg_replace('/Resource$/', '', $resourceName);
                    $components['resources'][$resourceName] = [
                        'path' => $file->getPathname(),
                        'relativePath' => $relativePath,
                        'type' => $fileType,
                        'name' => $resourceName,
                    ];
                } else {
                    $components['other'][] = [
                        'path' => $file->getPathname(),
                        'relativePath' => $relativePath,
                        'type' => $fileType,
                    ];
                }
            }
        }

        // Scan Pages directory (standalone pages, not resource pages)
        $pagesDir = $source.DIRECTORY_SEPARATOR.'Pages';
        if ($this->files->isDirectory($pagesDir)) {
            $finder = new Finder;
            $finder->files()->name('*.php')->in($pagesDir)->depth('== 0'); // Only direct children

            foreach ($finder as $file) {
                $content = $this->files->get($file->getPathname());
                $pageName = basename($file->getPathname(), '.php');

                // Skip if this extends a resource page class
                if (Str::contains($content, ['extends ListRecords', 'extends CreateRecord', 'extends EditRecord', 'extends ViewRecord', 'extends ManageRecords', 'extends ManageRelatedRecords'])) {
                    continue;
                }

                // Check if it's a Filament page
                if (Str::contains($content, 'extends Page') || Str::contains($content, 'extends Dashboard')) {
                    $components['pages'][$pageName] = [
                        'path' => $file->getPathname(),
                        'relativePath' => 'Pages'.DIRECTORY_SEPARATOR.$pageName.'.php',
                        'type' => 'standalone_page',
                        'name' => $pageName,
                    ];
                }
            }
        }

        // Scan Widgets directory
        $widgetsDir = $source.DIRECTORY_SEPARATOR.'Widgets';
        if ($this->files->isDirectory($widgetsDir)) {
            $finder = new Finder;
            $finder->files()->name('*.php')->in($widgetsDir);

            foreach ($finder as $file) {
                $content = $this->files->get($file->getPathname());
                $widgetName = basename($file->getPathname(), '.php');

                // Check if it's a Filament widget
                if (Str::contains($content, ['extends Widget', 'extends StatsOverviewWidget', 'extends ChartWidget', 'extends TableWidget'])) {
                    $components['widgets'][$widgetName] = [
                        'path' => $file->getPathname(),
                        'relativePath' => 'Widgets'.DIRECTORY_SEPARATOR.Str::after($file->getPathname(), $widgetsDir.DIRECTORY_SEPARATOR),
                        'type' => $this->detectWidgetType($content),
                        'name' => $widgetName,
                    ];
                }
            }
        }

        return $components;
    }

    /**
     * Detect widget type from content.
     */
    protected function detectWidgetType(string $content): string
    {
        if (Str::contains($content, 'extends StatsOverviewWidget')) {
            return 'stats_widget';
        }
        if (Str::contains($content, 'extends ChartWidget')) {
            return 'chart_widget';
        }
        if (Str::contains($content, 'extends TableWidget')) {
            return 'table_widget';
        }

        return 'widget';
    }

    /**
     * Select specific items to migrate.
     */
    protected function selectItems(string $type, array $items, string $label): array
    {
        $choice = select(
            label: "How would you like to migrate {$type}?",
            options: [
                'all' => 'All '.count($items).' items',
                'custom' => 'Select specific items',
            ],
            default: 'all',
        );

        if ($choice === 'all') {
            return array_keys($items);
        }

        $options = [];
        foreach ($items as $name => $item) {
            $typeLabel = isset($item['type']) && $item['type'] !== $type ? " ({$item['type']})" : '';
            $options[$name] = $name.$typeLabel;
        }

        return multiselect(
            label: $label,
            options: $options,
            hint: 'Use space to select, enter to confirm',
            required: true,
        );
    }

    /**
     * Process a standalone page file.
     */
    protected function processPage(array $page, string $source, string $target, string $panel, bool $dryRun, bool $force): void
    {
        $content = $this->files->get($page['path']);
        $targetPath = "{$target}/{$panel}/Pages/{$page['name']}.php";

        // Check if target exists
        if ($this->files->exists($targetPath) && ! $force) {
            $this->stats['skipped']++;

            return;
        }

        // Convert namespace
        $content = preg_replace(
            '/namespace\s+App\\\\Filament\\\\Pages\s*;/',
            "namespace App\\Laravilt\\{$panel}\\Pages;",
            $content
        );

        // Convert use statements and other content
        $content = $this->convertUseStatements($content);
        $content = $this->convertIcons($content);
        $content = $this->convertBackedEnum($content);
        $content = $this->convertMethodsAndProperties($content);

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($targetPath));
            $this->files->put($targetPath, $content);
        }

        $this->stats['pages']++;
    }

    /**
     * Process a widget file.
     */
    protected function processWidget(array $widget, string $source, string $target, string $panel, bool $dryRun, bool $force): void
    {
        $content = $this->files->get($widget['path']);
        $targetPath = "{$target}/{$panel}/Widgets/{$widget['name']}.php";

        // Check if target exists
        if ($this->files->exists($targetPath) && ! $force) {
            $this->stats['skipped']++;

            return;
        }

        // Convert namespace
        $content = preg_replace(
            '/namespace\s+App\\\\Filament\\\\Widgets\s*;/',
            "namespace App\\Laravilt\\{$panel}\\Widgets;",
            $content
        );

        // Handle nested namespace
        $content = preg_replace(
            '/namespace\s+App\\\\Filament\\\\Widgets\\\\([^;]+)\s*;/',
            "namespace App\\Laravilt\\{$panel}\\Widgets\\\\$1;",
            $content
        );

        // Convert use statements and other content
        $content = $this->convertUseStatements($content);
        $content = $this->convertIcons($content);
        $content = $this->convertBackedEnum($content);
        $content = $this->convertWidgetContent($content);

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($targetPath));
            $this->files->put($targetPath, $content);
        }

        $this->stats['widgets']++;
    }

    /**
     * Convert widget-specific content.
     */
    protected function convertWidgetContent(string $content): string
    {
        // Convert Stat:: to Laravilt format (Stat is directly in Widgets namespace, not Components)
        $content = preg_replace(
            '/Stat::make\(/',
            '\\Laravilt\\Widgets\\Stat::make(',
            $content
        );

        // Convert protected static ?string $heading
        $content = preg_replace(
            '/protected\s+static\s+\?string\s+\$heading\s*=/',
            'protected static ?string $heading =',
            $content
        );

        // Convert chart methods if needed
        $content = preg_replace(
            '/protected\s+function\s+getType\s*\(\s*\)\s*:\s*string/',
            'public function getType(): string',
            $content
        );

        // Convert getData method
        $content = preg_replace(
            '/protected\s+function\s+getData\s*\(\s*\)\s*:\s*array/',
            'public function getData(): array',
            $content
        );

        // Convert getStats method for StatsOverviewWidget
        $content = preg_replace(
            '/protected\s+function\s+getStats\s*\(\s*\)\s*:\s*array/',
            'public function getStats(): array',
            $content
        );

        // Convert getCards method (older Filament version)
        $content = preg_replace(
            '/protected\s+function\s+getCards\s*\(\s*\)\s*:\s*array/',
            'public function getStats(): array',
            $content
        );

        return $content;
    }

    protected function processFile(string $filePath, string $source, string $target, string $panel, bool $dryRun, bool $force): void
    {
        $content = $this->files->get($filePath);
        $relativePath = Str::after($filePath, $source.DIRECTORY_SEPARATOR);

        // Determine file type and target path
        $fileType = $this->detectFileType($content, $relativePath);
        $targetPath = $this->buildTargetPath($relativePath, $target, $panel, $fileType);

        // Check if target exists
        if ($this->files->exists($targetPath) && ! $force) {
            $this->stats['skipped']++;

            return;
        }

        // For resources, extract form/table/infolist into separate classes
        if ($fileType === 'resource' || $fileType === 'nested_resource') {
            $this->processResourceWithExtraction($content, $relativePath, $filePath, $target, $panel, $fileType, $dryRun, $force);

            return;
        }

        // Convert the content
        $convertedContent = $this->convertContent($content, $relativePath, $panel, $fileType);

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($targetPath));
            $this->files->put($targetPath, $convertedContent);
        }

        // Update stats
        match ($fileType) {
            'resource' => $this->stats['resources']++,
            'nested_resource' => $this->stats['nested_resources']++,
            'form' => $this->stats['forms']++,
            'table' => $this->stats['tables']++,
            'infolist' => $this->stats['infolists']++,
            'page' => $this->stats['pages']++,
            'relation_manager' => $this->stats['relation_managers']++,
            default => null,
        };
    }

    /**
     * Process a resource file and extract form/table/infolist into separate classes.
     */
    protected function processResourceWithExtraction(string $content, string $relativePath, string $sourcePath, string $target, string $panel, string $fileType, bool $dryRun, bool $force): void
    {
        // Get resource name from file name
        $fileName = basename($relativePath, '.php');
        $resourceName = preg_replace('/Resource$/', '', $fileName);

        // Build base path for the resource folder
        $basePath = "{$target}/{$panel}/Resources/{$resourceName}";
        $baseNamespace = "App\\Laravilt\\{$panel}\\Resources\\{$resourceName}";

        // Convert the main content first
        $convertedContent = $this->convertContent($content, $relativePath, $panel, $fileType);

        // Extract and create Form class
        if ($this->hasFormMethod($convertedContent)) {
            $formContent = $this->extractFormClass($content, $convertedContent, $resourceName, $baseNamespace, $sourcePath);
            $formPath = "{$basePath}/Form/{$resourceName}Form.php";

            if (! empty(trim($formContent)) && ! $dryRun && (! $this->files->exists($formPath) || $force)) {
                $this->files->ensureDirectoryExists(dirname($formPath));
                $this->files->put($formPath, $formContent);
                $this->stats['forms']++;
            }

            // Update resource to use the form class
            $convertedContent = $this->replaceFormMethodWithClass($convertedContent, $resourceName, $baseNamespace);
        }

        // Extract and create Table class
        if ($this->hasTableMethod($convertedContent)) {
            $tableContent = $this->extractTableClass($content, $convertedContent, $resourceName, $baseNamespace, $sourcePath);
            $tablePath = "{$basePath}/Table/{$resourceName}Table.php";

            if (! empty(trim($tableContent)) && ! $dryRun && (! $this->files->exists($tablePath) || $force)) {
                $this->files->ensureDirectoryExists(dirname($tablePath));
                $this->files->put($tablePath, $tableContent);
                $this->stats['tables']++;
            }

            // Update resource to use the table class
            $convertedContent = $this->replaceTableMethodWithClass($convertedContent, $resourceName, $baseNamespace);
        }

        // Extract and create Infolist class
        if ($this->hasInfolistMethod($convertedContent)) {
            $infolistContent = $this->extractInfolistClass($content, $convertedContent, $resourceName, $baseNamespace, $sourcePath);
            $infolistPath = "{$basePath}/Infolist/{$resourceName}Infolist.php";

            if (! empty(trim($infolistContent)) && ! $dryRun && (! $this->files->exists($infolistPath) || $force)) {
                $this->files->ensureDirectoryExists(dirname($infolistPath));
                $this->files->put($infolistPath, $infolistContent);
                $this->stats['infolists']++;
            }

            // Update resource to use the infolist class
            $convertedContent = $this->replaceInfolistMethodWithClass($convertedContent, $resourceName, $baseNamespace);
        }

        // Remove duplicate use statements after all the replacements
        $convertedContent = $this->removeDuplicateUseStatements($convertedContent);

        // Write the main resource file
        $resourcePath = "{$basePath}/{$fileName}.php";
        if (! $dryRun && (! $this->files->exists($resourcePath) || $force)) {
            $this->files->ensureDirectoryExists(dirname($resourcePath));
            $this->files->put($resourcePath, $convertedContent);
        }

        // Generate pages based on getPages() method
        $this->generateStandardPages($resourceName, $baseNamespace, $basePath, $dryRun, $force, $content);

        // Generate relation managers if defined
        $this->generateRelationManagers($content, $resourceName, $baseNamespace, $basePath, $dryRun, $force);

        // Update stats
        if ($fileType === 'nested_resource') {
            $this->stats['nested_resources']++;
        } else {
            $this->stats['resources']++;
        }
    }

    /**
     * Generate pages for a resource based on its getPages() method.
     */
    protected function generateStandardPages(string $resourceName, string $baseNamespace, string $basePath, bool $dryRun, bool $force, string $content = ''): void
    {
        $singularName = Str::singular($resourceName);

        // Try to detect which pages are used from getPages() method
        $detectedPages = $this->detectPagesFromContent($content, $resourceName, $singularName);

        // If no pages detected, use default CRUD pages
        if (empty($detectedPages)) {
            $detectedPages = [
                "List{$resourceName}" => 'ListRecords',
                "Create{$singularName}" => 'CreateRecord',
                "Edit{$singularName}" => 'EditRecord',
            ];
        }

        foreach ($detectedPages as $pageName => $baseClass) {
            $pagePath = "{$basePath}/Pages/{$pageName}.php";

            // Skip if page already exists and not forcing
            if ($this->files->exists($pagePath) && ! $force) {
                continue;
            }

            $pageContent = $this->generatePageContent($pageName, $resourceName, $baseNamespace, $baseClass);

            if (! $dryRun) {
                $this->files->ensureDirectoryExists(dirname($pagePath));
                $this->files->put($pagePath, $pageContent);
                $this->stats['pages']++;
            }
        }
    }

    /**
     * Detect which pages a resource uses from its getPages() method.
     */
    protected function detectPagesFromContent(string $content, string $resourceName, string $singularName): array
    {
        $pages = [];

        // Pattern to find getPages() method content
        if (! preg_match('/public\s+static\s+function\s+getPages\s*\(\s*\)\s*:\s*array\s*\{([^}]+)\}/s', $content, $matches)) {
            return $pages;
        }

        $pagesBody = $matches[1];

        // Detect ManageRecords (simple resource)
        if (preg_match('/\'index\'\s*=>\s*(\w*Manage\w+)::route/', $pagesBody, $match)) {
            $pages[$match[1]] = 'ManageRecords';

            return $pages; // ManageRecords is typically the only page for simple resources
        }

        // Detect ListRecords
        if (preg_match('/\'index\'\s*=>\s*(List\w+)::route/', $pagesBody, $match)) {
            $pages[$match[1]] = 'ListRecords';
        }

        // Detect CreateRecord
        if (preg_match('/\'create\'\s*=>\s*(Create\w+)::route/', $pagesBody, $match)) {
            $pages[$match[1]] = 'CreateRecord';
        }

        // Detect EditRecord
        if (preg_match('/\'edit\'\s*=>\s*(Edit\w+)::route/', $pagesBody, $match)) {
            $pages[$match[1]] = 'EditRecord';
        }

        // Detect ViewRecord
        if (preg_match('/\'view\'\s*=>\s*(View\w+)::route/', $pagesBody, $match)) {
            $pages[$match[1]] = 'ViewRecord';
        }

        // If we have ListRecords and CreateRecord but no EditRecord, add it (common pattern)
        $hasCreate = false;
        $hasEdit = false;
        foreach ($pages as $pageName => $baseClass) {
            if ($baseClass === 'CreateRecord') {
                $hasCreate = true;
            }
            if ($baseClass === 'EditRecord') {
                $hasEdit = true;
            }
        }
        if ($hasCreate && ! $hasEdit) {
            $pages["Edit{$singularName}"] = 'EditRecord';
        }

        // Detect ManageRelatedRecords pages (e.g., ListLicenses, ListInvitations)
        preg_match_all('/\'(\w+)\'\s*=>\s*(\w+)::route\s*\(\s*[\'"]\/\{parent\}/', $pagesBody, $relatedMatches, PREG_SET_ORDER);
        foreach ($relatedMatches as $match) {
            $pages[$match[2]] = 'ManageRelatedRecords';
        }

        return $pages;
    }

    /**
     * Generate page content.
     */
    protected function generatePageContent(string $pageName, string $resourceName, string $baseNamespace, string $baseClass): string
    {
        // Handle ManageRelatedRecords which needs additional properties
        if ($baseClass === 'ManageRelatedRecords') {
            // Extract relationship name from page name (e.g., ListLicenses -> licenses)
            $relationship = Str::of($pageName)
                ->replaceFirst('List', '')
                ->replaceFirst('Manage', '')
                ->camel()
                ->toString();

            return <<<PHP
<?php

namespace {$baseNamespace}\\Pages;

use {$baseNamespace}\\{$resourceName}Resource;
use Laravilt\\Panel\\Pages\\{$baseClass};

class {$pageName} extends {$baseClass}
{
    protected static ?string \$resource = {$resourceName}Resource::class;

    protected static string \$relationship = '{$relationship}';
}
PHP;
        }

        return <<<PHP
<?php

namespace {$baseNamespace}\\Pages;

use {$baseNamespace}\\{$resourceName}Resource;
use Laravilt\\Panel\\Pages\\{$baseClass};

class {$pageName} extends {$baseClass}
{
    protected static ?string \$resource = {$resourceName}Resource::class;
}
PHP;
    }

    /**
     * Generate relation managers defined in the resource.
     */
    protected function generateRelationManagers(string $content, string $resourceName, string $baseNamespace, string $basePath, bool $dryRun, bool $force): void
    {
        // Extract relation manager references from getRelations() method
        if (! preg_match('/public\s+static\s+function\s+getRelations\s*\(\s*\)\s*:\s*array\s*\{([^}]+)\}/s', $content, $matches)) {
            return;
        }

        $relationsBody = $matches[1];

        // Find relation manager class references
        if (preg_match_all('/([A-Z][a-zA-Z]+RelationManager)::class/', $relationsBody, $relationMatches)) {
            foreach ($relationMatches[1] as $relationManagerName) {
                $this->generateRelationManager($relationManagerName, $resourceName, $baseNamespace, $basePath, $dryRun, $force);
            }
        }
    }

    /**
     * Generate a relation manager class.
     */
    protected function generateRelationManager(string $name, string $resourceName, string $baseNamespace, string $basePath, bool $dryRun, bool $force): void
    {
        $path = "{$basePath}/RelationManagers/{$name}.php";

        // Skip if already exists and not forcing
        if ($this->files->exists($path) && ! $force) {
            return;
        }

        // Extract relationship name from class name (e.g., CustomersRelationManager -> customers)
        $relationshipName = Str::of($name)
            ->replaceLast('RelationManager', '')
            ->camel()
            ->plural()
            ->toString();

        $titleAttribute = 'name'; // Default, can be customized

        $content = <<<PHP
<?php

namespace {$baseNamespace}\\RelationManagers;

use Laravilt\\Actions\\DeleteAction;
use Laravilt\\Actions\\DeleteBulkAction;
use Laravilt\\Actions\\EditAction;
use Laravilt\\Actions\\BulkActionGroup;
use Laravilt\\Forms\\Components\\TextInput;
use Laravilt\\Panel\\Resources\\RelationManagers\\RelationManager;
use Laravilt\\Schemas\\Schema;
use Laravilt\\Tables\\Columns\\TextColumn;
use Laravilt\\Tables\\Table;

class {$name} extends RelationManager
{
    protected static string \$relationship = '{$relationshipName}';

    public function form(Schema \$form): Schema
    {
        return \$form
            ->schema([
                TextInput::make('{$titleAttribute}')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table \$table): Table
    {
        return \$table
            ->columns([
                TextColumn::make('{$titleAttribute}'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
PHP;

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $content);
            $this->stats['relation_managers']++;
        }
    }

    protected function hasFormMethod(string $content): bool
    {
        return (bool) preg_match('/public\s+static\s+function\s+form\s*\(/s', $content);
    }

    protected function hasTableMethod(string $content): bool
    {
        return (bool) preg_match('/public\s+static\s+function\s+table\s*\(/s', $content);
    }

    protected function hasInfolistMethod(string $content): bool
    {
        return (bool) preg_match('/public\s+static\s+function\s+infolist\s*\(/s', $content);
    }

    /**
     * Extract the form method content and create a standalone Form class.
     */
    protected function extractFormClass(string $originalContent, string $convertedContent, string $resourceName, string $baseNamespace, string $sourcePath): string
    {
        // Check if the form method delegates to a class-based structure
        $delegatedClass = $this->getDelegatedClassName($originalContent, 'form');

        if ($delegatedClass) {
            // Find the class file
            $classFilePath = $this->findClassBasedStructureFile($delegatedClass, $sourcePath, 'form');

            if ($classFilePath) {
                // Read and extract content from the class file
                $classContent = $this->files->get($classFilePath);
                $formBody = $this->extractClassBasedMethodBody($classFilePath);
                $useStatements = $this->extractClassBasedUseStatements($classFilePath);

                // Convert the content (use statements and body)
                $useStatements = $this->convertUseStatements($useStatements);
                $formBody = $this->convertUseStatements($formBody);
                $formBody = $this->convertIcons($formBody);
                $formBody = $this->convertBackedEnum($formBody);
                $formBody = $this->convertMethodsAndProperties($formBody);
            } else {
                // Class file not found, return empty
                return '';
            }
        } else {
            // Extract the form method body from the converted content
            $formBody = $this->extractMethodBody($convertedContent, 'form');

            if (empty($formBody)) {
                return '';
            }

            // Extract use statements that are relevant for forms
            $useStatements = $this->extractRelevantUseStatements($convertedContent, $formBody);
        }

        // Remove any use statement for Schema since we add it explicitly
        $useStatements = preg_replace('/^use\s+Laravilt\\\\Schemas\\\\Schema;\s*\n?/m', '', $useStatements);
        $useStatements = trim($useStatements);

        // Check if Get or Set are used in the form body
        $additionalUses = '';
        if (preg_match('/\bGet\s+\$get\b/', $formBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Get;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Get;\n";
        }
        if (preg_match('/\bSet\s+\$set\b/', $formBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Set;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Set;\n";
        }

        $formClass = <<<PHP
<?php

namespace {$baseNamespace}\\Form;

{$useStatements}
{$additionalUses}use Laravilt\\Schemas\\Schema;

class {$resourceName}Form
{
    public static function make(Schema \$schema): Schema
    {
        {$formBody}
    }
}
PHP;

        // Clean up multiple blank lines
        $formClass = preg_replace('/\n{3,}/', "\n\n", $formClass);

        return $formClass;
    }

    /**
     * Extract the table method content and create a standalone Table class.
     */
    protected function extractTableClass(string $originalContent, string $convertedContent, string $resourceName, string $baseNamespace, string $sourcePath): string
    {
        // Check if the table method delegates to a class-based structure
        $delegatedClass = $this->getDelegatedClassName($originalContent, 'table');

        if ($delegatedClass) {
            // Find the class file
            $classFilePath = $this->findClassBasedStructureFile($delegatedClass, $sourcePath, 'table');

            if ($classFilePath) {
                // Read and extract content from the class file
                $tableBody = $this->extractClassBasedMethodBody($classFilePath);
                $useStatements = $this->extractClassBasedUseStatements($classFilePath);

                // Convert the content (use statements and body)
                $useStatements = $this->convertUseStatements($useStatements);
                $tableBody = $this->convertUseStatements($tableBody);
                $tableBody = $this->convertIcons($tableBody);
                $tableBody = $this->convertBackedEnum($tableBody);
                $tableBody = $this->convertMethodsAndProperties($tableBody);
            } else {
                // Class file not found, return empty
                return '';
            }
        } else {
            // Extract the table method body from the converted content
            $tableBody = $this->extractMethodBody($convertedContent, 'table');

            if (empty($tableBody)) {
                return '';
            }

            // Extract use statements that are relevant for tables
            $useStatements = $this->extractRelevantUseStatements($convertedContent, $tableBody);
        }

        // Remove any use statement for Table since we add it explicitly
        $useStatements = preg_replace('/^use\s+Laravilt\\\\Tables\\\\Table;\s*\n?/m', '', $useStatements);
        $useStatements = trim($useStatements);

        // Check if Get or Set are used in the table body
        $additionalUses = '';
        if (preg_match('/\bGet\s+\$get\b/', $tableBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Get;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Get;\n";
        }
        if (preg_match('/\bSet\s+\$set\b/', $tableBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Set;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Set;\n";
        }

        $tableClass = <<<PHP
<?php

namespace {$baseNamespace}\\Table;

{$useStatements}
{$additionalUses}use Laravilt\\Tables\\Table;

class {$resourceName}Table
{
    public static function make(Table \$table): Table
    {
        {$tableBody}
    }
}
PHP;

        // Clean up multiple blank lines
        $tableClass = preg_replace('/\n{3,}/', "\n\n", $tableClass);

        return $tableClass;
    }

    /**
     * Extract the infolist method content and create a standalone Infolist class.
     */
    protected function extractInfolistClass(string $originalContent, string $convertedContent, string $resourceName, string $baseNamespace, string $sourcePath): string
    {
        // Check if the infolist method delegates to a class-based structure
        $delegatedClass = $this->getDelegatedClassName($originalContent, 'infolist');

        if ($delegatedClass) {
            // Find the class file
            $classFilePath = $this->findClassBasedStructureFile($delegatedClass, $sourcePath, 'infolist');

            if ($classFilePath) {
                // Read and extract content from the class file
                $infolistBody = $this->extractClassBasedMethodBody($classFilePath);
                $useStatements = $this->extractClassBasedUseStatements($classFilePath);

                // Convert the content (use statements and body)
                $useStatements = $this->convertUseStatements($useStatements);
                $infolistBody = $this->convertUseStatements($infolistBody);
                $infolistBody = $this->convertIcons($infolistBody);
                $infolistBody = $this->convertBackedEnum($infolistBody);
                $infolistBody = $this->convertMethodsAndProperties($infolistBody);
            } else {
                // Class file not found, return empty
                return '';
            }
        } else {
            // Extract the infolist method body from the converted content
            $infolistBody = $this->extractMethodBody($convertedContent, 'infolist');

            if (empty($infolistBody)) {
                return '';
            }

            // Extract use statements that are relevant for infolists
            $useStatements = $this->extractRelevantUseStatements($convertedContent, $infolistBody);
        }

        // Remove any use statement for Schema since we add it explicitly
        $useStatements = preg_replace('/^use\s+Laravilt\\\\Schemas\\\\Schema;\s*\n?/m', '', $useStatements);
        $useStatements = preg_replace('/^use\s+Filament\\\\Schemas\\\\Schema;\s*\n?/m', '', $useStatements);
        $useStatements = trim($useStatements);

        // Check if Get or Set are used in the infolist body
        $additionalUses = '';
        if (preg_match('/\bGet\s+\$get\b/', $infolistBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Get;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Get;\n";
        }
        if (preg_match('/\bSet\s+\$set\b/', $infolistBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Set;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Set;\n";
        }

        // Laravilt uses Schema for infolists (same as Filament v4)
        $infolistClass = <<<PHP
<?php

namespace {$baseNamespace}\\Infolist;

{$useStatements}
{$additionalUses}use Laravilt\\Schemas\\Schema;

class {$resourceName}Infolist
{
    public static function make(Schema \$schema): Schema
    {
        {$infolistBody}
    }
}
PHP;

        // Clean up multiple blank lines
        $infolistClass = preg_replace('/\n{3,}/', "\n\n", $infolistClass);

        return $infolistClass;
    }

    /**
     * Extract the body of a method.
     * If the method delegates to a class-based structure, return empty to skip extraction.
     */
    protected function extractMethodBody(string $content, string $methodName): string
    {
        // Pattern to match the entire method including its body
        $pattern = '/public\s+static\s+function\s+'.$methodName.'\s*\([^)]*\)\s*:\s*\w+\s*\{/s';

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startPos = $matches[0][1] + strlen($matches[0][0]);
        $braceCount = 1;
        $pos = $startPos;
        $len = strlen($content);

        // Find the matching closing brace
        while ($pos < $len && $braceCount > 0) {
            $char = $content[$pos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
            }
            $pos++;
        }

        $body = substr($content, $startPos, $pos - $startPos - 1);
        $body = trim($body);

        return $body;
    }

    /**
     * Check if a method body delegates to a class-based structure.
     *
     * @return string|null The class name if it delegates, null otherwise
     */
    protected function getDelegatedClassName(string $content, string $methodName): ?string
    {
        $body = $this->extractMethodBody($content, $methodName);

        // Match patterns like "return CustomerForm::configure($schema);" or "return CustomerTable::make($table);"
        if (preg_match('/^\s*return\s+(\w+(?:Form|Table|Infolist))::(?:configure|make)\s*\(\s*\$\w+\s*\)\s*;?\s*$/s', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Find the source file for a class-based structure.
     *
     * @param  string  $className  The class name (e.g., CustomerForm)
     * @param  string  $resourcePath  The resource file path
     * @param  string  $type  The type (form, table, infolist)
     * @return string|null The file path if found, null otherwise
     */
    protected function findClassBasedStructureFile(string $className, string $resourcePath, string $type): ?string
    {
        $resourceDir = dirname($resourcePath);

        // Common locations for class-based structures
        $possiblePaths = [
            // Direct subfolder (Form/, Table/, Infolist/)
            "{$resourceDir}/".ucfirst($type)."/{$className}.php",
            // Schemas folder (for forms in v4)
            "{$resourceDir}/Schemas/{$className}.php",
            // Tables folder
            "{$resourceDir}/Tables/{$className}.php",
            // Infolists folder
            "{$resourceDir}/Infolists/{$className}.php",
            // Forms folder
            "{$resourceDir}/Forms/{$className}.php",
            // Same directory as resource
            "{$resourceDir}/{$className}.php",
        ];

        foreach ($possiblePaths as $path) {
            if ($this->files->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Extract the method body from a class-based structure file.
     *
     * @param  string  $filePath  The path to the class file
     * @param  string  $methodName  The method name to extract (e.g., 'configure', 'make')
     * @return string The method body content
     */
    protected function extractClassBasedMethodBody(string $filePath, string $methodName = 'configure'): string
    {
        $content = $this->files->get($filePath);

        // Try multiple method names that class-based structures might use
        $methodNames = ['configure', 'make', 'schema', 'columns', 'entries'];

        foreach ($methodNames as $name) {
            $body = $this->extractMethodBodyFromClassFile($content, $name);
            if (! empty($body)) {
                return $body;
            }
        }

        return '';
    }

    /**
     * Extract method body from a class file content.
     */
    protected function extractMethodBodyFromClassFile(string $content, string $methodName): string
    {
        // Pattern to match static or non-static methods
        $pattern = '/public\s+(?:static\s+)?function\s+'.$methodName.'\s*\([^)]*\)(?:\s*:\s*\w+)?\s*\{/s';

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startPos = $matches[0][1] + strlen($matches[0][0]);
        $braceCount = 1;
        $pos = $startPos;
        $len = strlen($content);

        // Find the matching closing brace
        while ($pos < $len && $braceCount > 0) {
            $char = $content[$pos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
            }
            $pos++;
        }

        return trim(substr($content, $startPos, $pos - $startPos - 1));
    }

    /**
     * Extract use statements from a class-based structure file.
     */
    protected function extractClassBasedUseStatements(string $filePath): string
    {
        $content = $this->files->get($filePath);
        preg_match_all('/^use\s+[^;]+;$/m', $content, $matches);

        return implode("\n", $matches[0] ?? []);
    }

    /**
     * Extract relevant use statements for a method body.
     */
    protected function extractRelevantUseStatements(string $content, string $methodBody): string
    {
        preg_match_all('/^use\s+([^;]+);$/m', $content, $matches);

        $relevantUses = [];
        foreach ($matches[1] as $use) {
            $className = Str::afterLast($use, '\\');
            $alias = $className;

            // Check if there's an alias
            if (Str::contains($use, ' as ')) {
                [$use, $alias] = explode(' as ', $use);
                $alias = trim($alias);
            }

            // Check if this class is used in the method body
            if (Str::contains($methodBody, $alias)) {
                $relevantUses[] = "use {$use}".(Str::contains($matches[0][array_search($use, $matches[1])], ' as ') ? " as {$alias}" : '').';';
            }
        }

        return implode("\n", array_unique($relevantUses));
    }

    /**
     * Find the end position of a method by counting braces.
     */
    protected function findMethodEnd(string $content, int $startPos): int
    {
        $len = strlen($content);
        $braceCount = 0;
        $pos = $startPos;
        $inMethod = false;

        while ($pos < $len) {
            $char = $content[$pos];

            if ($char === '{') {
                $braceCount++;
                $inMethod = true;
            } elseif ($char === '}') {
                $braceCount--;
                if ($inMethod && $braceCount === 0) {
                    return $pos + 1; // Include the closing brace
                }
            }
            $pos++;
        }

        return $pos;
    }

    /**
     * Replace the form method with a call to the Form class.
     */
    protected function replaceFormMethodWithClass(string $content, string $resourceName, string $baseNamespace): string
    {
        // Add use statement for the Form class
        $formUse = "use {$baseNamespace}\\Form\\{$resourceName}Form;";
        if (! Str::contains($content, $formUse)) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;\s*\n)/',
                "$1\n{$formUse}\n",
                $content
            );
        }

        // Find the form method and replace it using brace counting
        $pattern = '/public\s+static\s+function\s+form\s*\([^)]*\)\s*:\s*Schema\s*/s';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $methodStart = $matches[0][1];
            $methodEnd = $this->findMethodEnd($content, $methodStart);

            $replacement = <<<PHP
public static function form(Schema \$schema): Schema
    {
        return {$resourceName}Form::make(\$schema);
    }
PHP;

            $content = substr($content, 0, $methodStart).$replacement.substr($content, $methodEnd);
        }

        return $content;
    }

    /**
     * Replace the table method with a call to the Table class.
     */
    protected function replaceTableMethodWithClass(string $content, string $resourceName, string $baseNamespace): string
    {
        // Add use statement for the Table class
        $tableUse = "use {$baseNamespace}\\Table\\{$resourceName}Table;";
        if (! Str::contains($content, $tableUse)) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;\s*\n)/',
                "$1\n{$tableUse}\n",
                $content
            );
        }

        // Find the table method and replace it using brace counting
        $pattern = '/public\s+static\s+function\s+table\s*\([^)]*\)\s*:\s*Table\s*/s';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $methodStart = $matches[0][1];
            $methodEnd = $this->findMethodEnd($content, $methodStart);

            $replacement = <<<PHP
public static function table(Table \$table): Table
    {
        return {$resourceName}Table::make(\$table);
    }
PHP;

            $content = substr($content, 0, $methodStart).$replacement.substr($content, $methodEnd);
        }

        return $content;
    }

    /**
     * Replace the infolist method with a call to the Infolist class.
     */
    protected function replaceInfolistMethodWithClass(string $content, string $resourceName, string $baseNamespace): string
    {
        // Add use statement for the Infolist class
        $infolistUse = "use {$baseNamespace}\\Infolist\\{$resourceName}Infolist;";
        if (! Str::contains($content, $infolistUse)) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;\s*\n)/',
                "$1\n{$infolistUse}\n",
                $content
            );
        }

        // Find the infolist method and replace it using brace counting
        // Laravilt uses Schema for infolists (same as Filament v4)
        $pattern = '/public\s+static\s+function\s+infolist\s*\([^)]*\)\s*:\s*(?:Infolist|Schema)\s*/s';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $methodStart = $matches[0][1];
            $methodEnd = $this->findMethodEnd($content, $methodStart);

            $replacement = <<<PHP
public static function infolist(Schema \$schema): Schema
    {
        return {$resourceName}Infolist::make(\$schema);
    }
PHP;

            $content = substr($content, 0, $methodStart).$replacement.substr($content, $methodEnd);
        }

        return $content;
    }

    protected function detectFileType(string $content, string $relativePath): string
    {
        // Check for specific patterns
        if (Str::contains($content, 'extends Resource')) {
            // Check if this is a nested resource (inside another resource's Resources folder)
            // Pattern: Resources/Parent/Resources/Child/ChildResource.php
            if (preg_match('/Resources\/[^\/]+\/Resources\//', $relativePath)) {
                return 'nested_resource';
            }

            return 'resource';
        }

        if (Str::contains($content, 'extends RelationManager')) {
            return 'relation_manager';
        }

        if (Str::contains($content, ['extends ListRecords', 'extends CreateRecord', 'extends EditRecord', 'extends ViewRecord', 'extends ManageRecords', 'extends ManageRelatedRecords'])) {
            return 'page';
        }

        // Check by directory structure
        if (Str::contains($relativePath, '/Schemas/') || Str::contains($relativePath, '/Form/')) {
            if (Str::contains($content, 'Infolist') || Str::endsWith($relativePath, 'Infolist.php')) {
                return 'infolist';
            }

            return 'form';
        }

        if (Str::contains($relativePath, '/Tables/') || Str::contains($relativePath, '/Table/')) {
            return 'table';
        }

        if (Str::contains($relativePath, '/Pages/')) {
            return 'page';
        }

        if (Str::contains($relativePath, '/RelationManagers/')) {
            return 'relation_manager';
        }

        // Check content for Form components
        if (Str::contains($content, ['TextInput::', 'Textarea::', 'Select::', 'Toggle::', 'DatePicker::', 'DateTimePicker::'])) {
            if (! Str::contains($content, 'extends Resource')) {
                return 'form';
            }
        }

        // Check content for Table components
        if (Str::contains($content, ['TextColumn::', 'IconColumn::', '->columns(['])) {
            if (! Str::contains($content, 'extends Resource')) {
                return 'table';
            }
        }

        // Check for infolist entries
        if (Str::contains($content, ['TextEntry::', 'IconEntry::', 'ImageEntry::'])) {
            if (! Str::contains($content, 'extends Resource')) {
                return 'infolist';
            }
        }

        return 'other';
    }

    protected function buildTargetPath(string $relativePath, string $target, string $panel, string $fileType): string
    {
        // Transform path structure:
        // app/Filament/Resources/CustomerResource.php
        // → app/Laravilt/Admin/Resources/Customer/CustomerResource.php
        //
        // app/Filament/Resources/CustomerResource/Pages/ListCustomers.php
        // → app/Laravilt/Admin/Resources/Customer/Pages/ListCustomers.php

        $path = $relativePath;

        // Remove "Resources/" prefix if present
        $path = preg_replace('#^Resources/#', '', $path);

        // Normalize directory names
        $parts = explode('/', $path);
        $filename = array_pop($parts);

        // Determine resource name from filename or path
        $resourceName = null;
        if ($fileType === 'resource' || $fileType === 'nested_resource') {
            // CustomerResource.php → Customer
            $resourceName = preg_replace('/Resource\.php$/', '', $filename);
        } elseif (count($parts) > 0) {
            // Get from path: CustomerResource/Pages/ListCustomers.php → Customer
            $firstPart = $parts[0];
            if (Str::endsWith($firstPart, 'Resource')) {
                $resourceName = preg_replace('/Resource$/', '', $firstPart);
            }
        }

        // Convert plural directory to singular if it matches the resource pattern
        $newParts = [];
        $skipFirst = false;

        foreach ($parts as $i => $part) {
            // Skip the first part if it's "CustomerResource" style (we'll use resourceName)
            if ($i === 0 && Str::endsWith($part, 'Resource')) {
                $skipFirst = true;

                continue;
            }

            // Skip Schemas folder - we'll reorganize
            if ($part === 'Schemas') {
                continue;
            }

            // Convert Tables to Table
            if ($part === 'Tables') {
                $newParts[] = 'Table';

                continue;
            }

            // Handle resource folder names (singularize)
            if (Str::endsWith($part, 's') && ! in_array($part, ['Pages', 'Resources', 'RelationManagers', 'NestedResources'])) {
                $singular = Str::singular($part);
                // Only singularize if it makes sense
                if (strlen($singular) > 2) {
                    $newParts[] = $singular;

                    continue;
                }
            }

            $newParts[] = $part;
        }

        // Determine subfolder based on file type
        $typeFolder = match ($fileType) {
            'form' => 'Form',
            'table' => 'Table',
            'infolist' => 'Infolist',
            'page' => 'Pages',
            'relation_manager' => 'RelationManagers',
            default => '',
        };

        // Build the final path
        $finalParts = [];

        // Add resource folder first
        if ($resourceName) {
            $finalParts[] = $resourceName;
        }

        // Add existing path parts (which should be type folders like Pages, RelationManagers)
        foreach ($newParts as $part) {
            $finalParts[] = $part;
        }

        // Reorganize structure - add type folder if needed for non-resource files
        if (in_array($fileType, ['form', 'infolist', 'table', 'page', 'relation_manager'])) {
            $hasTypeFolder = false;
            $typeFolders = ['Form', 'Table', 'Infolist', 'Pages', 'RelationManagers'];
            foreach ($finalParts as $p) {
                if (in_array($p, $typeFolders)) {
                    $hasTypeFolder = true;
                    break;
                }
            }
            if (! $hasTypeFolder && $typeFolder) {
                $finalParts[] = $typeFolder;
            }
        }

        $path = implode('/', $finalParts);
        $path = $path ? $path.'/'.$filename : $filename;

        // Build final target path
        return "{$target}/{$panel}/Resources/{$path}";
    }

    protected function convertContent(string $content, string $relativePath, string $panel, string $fileType): string
    {
        // Update namespace
        $content = $this->convertNamespace($content, $relativePath, $panel, $fileType);

        // Update use statements
        $content = $this->convertUseStatements($content);

        // Convert nested resources to extend NestedResource
        if ($fileType === 'nested_resource') {
            $content = $this->convertToNestedResource($content, $relativePath);
        }

        // Convert Heroicon references to string icons
        $content = $this->convertIcons($content);

        // Convert BackedEnum type hints
        $content = $this->convertBackedEnum($content);

        // Convert method names and property types
        $content = $this->convertMethodsAndProperties($content);

        // Fix any class references
        $content = $this->fixClassReferences($content, $panel);

        return $content;
    }

    /**
     * Convert a nested resource to extend NestedResource instead of Resource.
     */
    protected function convertToNestedResource(string $content, string $relativePath): string
    {
        // Change extends Resource to extends NestedResource
        $content = preg_replace(
            '/extends\s+Resource\b/',
            'extends NestedResource',
            $content
        );

        // Update the use statement
        $content = preg_replace(
            '/use\s+Laravilt\\\\Panel\\\\Resources\\\\Resource\s*;/',
            'use Laravilt\\Panel\\Resources\\NestedResource;',
            $content
        );

        // Extract parent resource from path
        // Pattern: Resources/Parent/Resources/Child/ChildResource.php
        if (preg_match('/Resources\/([^\/]+)\/Resources\/([^\/]+)\//', $relativePath, $matches)) {
            $parentName = $matches[1];
            $childName = $matches[2];

            // Add parentResource property if not exists
            if (! Str::contains($content, '$parentResource')) {
                // Find the class declaration and add the property after the model property
                $content = preg_replace(
                    '/(protected\s+static\s+string\s+\$model\s*=\s*[^;]+;)/',
                    "$1\n\n    protected static ?string \$parentResource = {$parentName}Resource::class;\n\n    protected static string \$parentRelationship = '".Str::camel($parentName)."';",
                    $content
                );
            }
        }

        return $content;
    }

    protected function convertMethodsAndProperties(string $content): string
    {
        // Convert ->components([ to ->schema([
        // This is the Filament v4 way of defining schema components
        $content = preg_replace('/->components\(\[/', '->schema([', $content);

        // Convert protected static ?string $model to protected static string $model
        // Laravilt Resource expects string, not ?string
        $content = preg_replace(
            '/protected\s+static\s+\?string\s+\$model\s*=/',
            'protected static string $model =',
            $content
        );

        // Convert protected static ?int $navigationSort to protected static int $navigationSort
        // Laravilt expects int, not ?int
        $content = preg_replace(
            '/protected\s+static\s+\?int\s+\$navigationSort\s*=/',
            'protected static int $navigationSort =',
            $content
        );

        // Convert protected static string $resource to protected static ?string $resource
        // Laravilt Page expects ?string, not string
        $content = preg_replace(
            '/protected\s+static\s+string\s+\$resource\s*=/',
            'protected static ?string $resource =',
            $content
        );

        // Convert protected function getHeaderActions() to public function getHeaderActions()
        // Laravilt expects public visibility
        $content = preg_replace(
            '/protected\s+function\s+getHeaderActions\s*\(\s*\)\s*:\s*array/',
            'public function getHeaderActions(): array',
            $content
        );

        // Convert protected function getTitle() to public static function getTitle()
        // Laravilt expects static getTitle
        $content = preg_replace(
            '/protected\s+function\s+getTitle\s*\(\s*\)\s*:\s*string/',
            'public static function getTitle(): string',
            $content
        );

        // Convert protected function getActions() to public function getHeaderActions()
        // Filament v3 uses getActions(), Laravilt uses getHeaderActions()
        $content = preg_replace(
            '/protected\s+function\s+getActions\s*\(\s*\)\s*:\s*array/',
            'public function getHeaderActions(): array',
            $content
        );

        // Convert protected static functions to public static functions
        // Laravilt expects public visibility for these resource methods
        $protectedStaticMethods = [
            'getNavigationIcon',
            'getNavigationLabel',
            'getNavigationGroup',
            'getNavigationSort',
            'getNavigationBadge',
            'getNavigationBadgeColor',
            'getModelLabel',
            'getPluralModelLabel',
            'getSlug',
            'getRecordTitle',
            'getGloballySearchableAttributes',
            'getGlobalSearchResultDetails',
            'getGlobalSearchResultTitle',
            'getGlobalSearchResultUrl',
            'getGlobalSearchEloquentQuery',
            'canCreate',
            'canEdit',
            'canDelete',
            'canView',
            'canViewAny',
        ];

        foreach ($protectedStaticMethods as $method) {
            $content = preg_replace(
                '/protected\s+static\s+function\s+'.$method.'\s*\(/s',
                'public static function '.$method.'(',
                $content
            );
        }

        // Convert ButtonAction to Action
        $content = str_replace('ButtonAction::', 'Action::', $content);
        $content = preg_replace('/use\s+Filament\\\\Pages\\\\Actions\\\\ButtonAction;\s*\n?/', "use Laravilt\\Actions\\Action;\n", $content);

        // Convert protected mutate methods to public
        // Laravilt expects public visibility for these methods
        $mutateMethods = [
            'mutateFormDataBeforeCreate',
            'mutateFormDataBeforeSave',
            'mutateFormDataBeforeFill',
            'afterCreate',
            'afterSave',
            'beforeCreate',
            'beforeSave',
            'beforeFill',
            'afterFill',
        ];

        foreach ($mutateMethods as $method) {
            $content = preg_replace(
                '/protected\s+function\s+'.$method.'\s*\(/',
                'public function '.$method.'(',
                $content
            );
        }

        // Convert mount($record) to mount($record = null) for compatibility
        // The base Page class expects an optional parameter
        $content = preg_replace(
            '/function\s+mount\s*\(\s*\$record\s*\)\s*:\s*void/',
            'function mount($record = null): void',
            $content
        );

        // Remove ->deferLoading() and ->deferFilters() - Laravilt tables always defer by default
        $content = preg_replace('/\s*->deferLoading\(\)\s*/', '', $content);
        $content = preg_replace('/\s*->deferFilters\(\)\s*/', '', $content);

        // Convert Filament Blade $view property to Laravilt Vue $inertiaPage
        // protected static string $view = 'filament.resources.x.pages.y' → protected static ?string $inertiaPage = 'laravilt/Custom/Y'
        $content = preg_replace_callback(
            "/protected\s+static\s+string\s+\\\$view\s*=\s*['\"]([^'\"]+)['\"];/",
            function ($matches) {
                $bladePath = $matches[1];
                // Convert filament.resources.boards.pages.view-board to laravilt/Custom/ViewBoard
                $parts = explode('.', $bladePath);
                $pageName = end($parts);
                // Convert kebab-case to StudlyCase
                $pageName = str_replace(' ', '', ucwords(str_replace('-', ' ', $pageName)));

                return "protected static ?string \$inertiaPage = 'laravilt/Custom/{$pageName}';";
            },
            $content
        );

        // Remove the $view property entirely if it's a simple filament view (comment out for now)
        // $content = preg_replace("/\s*protected\s+static\s+string\s+\\\$view\s*=\s*['\"][^'\"]+['\"];\s*\n?/", "\n", $content);

        // Convert table ->actions([ to ->recordActions([
        // Laravilt uses recordActions for row-level actions
        // Handle both ->actions([ and ->actions(\n[ patterns
        $content = preg_replace('/->actions\s*\(\s*\[/', '->recordActions([', $content);

        // Convert table ->bulkActions([ to ->toolbarActions([
        // Laravilt uses toolbarActions for bulk/toolbar actions
        $content = preg_replace('/->bulkActions\s*\(\s*\[/', '->toolbarActions([', $content);

        // Convert form(Form $form) to form(Schema $schema) for RelationManagers
        // Filament uses Form, Laravilt uses Schema
        $content = preg_replace(
            '/function\s+form\s*\(\s*Form\s+\$form\s*\)\s*:\s*Form/',
            'function form(Schema $schema): Schema',
            $content
        );

        // Also handle Schema $form (after namespace conversion)
        $content = preg_replace(
            '/function\s+form\s*\(\s*Schema\s+\$form\s*\)\s*:\s*Schema/',
            'function form(Schema $schema): Schema',
            $content
        );

        // Convert return $form->schema([ to return $schema->schema([
        $content = preg_replace(
            '/return\s+\$form\s*\n?\s*->schema\s*\(\[/',
            'return $schema->schema([',
            $content
        );

        // Also handle single-line: return $form->schema([
        $content = preg_replace('/return\s+\$form->schema\(\[/', 'return $schema->schema([', $content);

        // Replace remaining $form references with $schema in form methods
        // This is done carefully to avoid replacing in other contexts
        $content = preg_replace('/return\s+\$form\s*;/', 'return $schema;', $content);
        $content = preg_replace('/\$form->/', '$schema->', $content);

        // Convert ColorSwatch to ColorColumn
        $content = preg_replace(
            '/ColorSwatch::make\(/',
            'ColorColumn::make(',
            $content
        );

        // Convert SpatieMediaLibraryFileUpload to FileUpload with collection
        // SpatieMediaLibraryFileUpload::make('avatar')->collection('avatars')
        // => FileUpload::make('avatar')->collection('avatars')
        $content = preg_replace(
            '/SpatieMediaLibraryFileUpload::make\(/',
            'FileUpload::make(',
            $content
        );

        // Convert TinyEditor to RichEditor
        $content = preg_replace(
            '/TinyEditor::make\(/',
            'RichEditor::make(',
            $content
        );

        // Convert TiptapEditor to RichEditor
        $content = preg_replace(
            '/TiptapEditor::make\(/',
            'RichEditor::make(',
            $content
        );

        // Convert Closure $get to Get $get and Closure $set to Set $set
        // Filament uses Closure type hints, Laravilt uses Get/Set utilities
        $content = preg_replace('/Closure\s+\$get\b/', 'Get $get', $content);
        $content = preg_replace('/Closure\s+\$set\b/', 'Set $set', $content);

        // Convert PopupPosition enum to string values
        // RVxLab\FilamentColorPicker uses enum with method-style calls like PopupPosition::BOTTOM()
        $popupPositionMap = [
            'PopupPosition::TOP()' => "'top'",
            'PopupPosition::TOP_START()' => "'top-start'",
            'PopupPosition::TOP_END()' => "'top-end'",
            'PopupPosition::BOTTOM()' => "'bottom'",
            'PopupPosition::BOTTOM_START()' => "'bottom-start'",
            'PopupPosition::BOTTOM_END()' => "'bottom-end'",
            'PopupPosition::LEFT()' => "'left'",
            'PopupPosition::LEFT_START()' => "'left-start'",
            'PopupPosition::LEFT_END()' => "'left-end'",
            'PopupPosition::RIGHT()' => "'right'",
            'PopupPosition::RIGHT_START()' => "'right-start'",
            'PopupPosition::RIGHT_END()' => "'right-end'",
            // Also handle constant-style without parentheses
            'PopupPosition::TOP' => "'top'",
            'PopupPosition::TOP_START' => "'top-start'",
            'PopupPosition::TOP_END' => "'top-end'",
            'PopupPosition::BOTTOM' => "'bottom'",
            'PopupPosition::BOTTOM_START' => "'bottom-start'",
            'PopupPosition::BOTTOM_END' => "'bottom-end'",
            'PopupPosition::LEFT' => "'left'",
            'PopupPosition::LEFT_START' => "'left-start'",
            'PopupPosition::LEFT_END' => "'left-end'",
            'PopupPosition::RIGHT' => "'right'",
            'PopupPosition::RIGHT_START' => "'right-start'",
            'PopupPosition::RIGHT_END' => "'right-end'",
        ];

        foreach ($popupPositionMap as $enum => $string) {
            $content = str_replace($enum, $string, $content);
        }

        // Add use statements for Get and Set if closures were converted
        if (preg_match('/\bGet\s+\$get\b/', $content) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Get;/', $content)) {
            $content = preg_replace(
                '/^(namespace\s+[^;]+;\s*\n)/m',
                "$1\nuse Laravilt\\Support\\Utilities\\Get;",
                $content
            );
        }
        if (preg_match('/\bSet\s+\$set\b/', $content) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Set;/', $content)) {
            $content = preg_replace(
                '/^(namespace\s+[^;]+;\s*\n)/m',
                "$1\nuse Laravilt\\Support\\Utilities\\Set;",
                $content
            );
        }

        // Convert BelongsToSelect to Select
        $content = preg_replace(
            '/BelongsToSelect::make\(/',
            'Select::make(',
            $content
        );

        // Convert BelongsToManyMultiSelect to Select with multiple
        $content = preg_replace(
            '/BelongsToManyMultiSelect::make\(([^)]+)\)/',
            'Select::make($1)->multiple()',
            $content
        );

        // Convert Card to Section
        $content = preg_replace(
            '/Card::make\(\)/',
            'Section::make()',
            $content
        );

        // Convert ->enum() to ->options() for TextColumn (Laravilt uses formatStateUsing or options)
        // TextColumn::make('type')->enum(['key' => 'Label']) stays as is but needs options method
        // This is handled differently - we add a comment for manual review
        // Actually, let's convert it to formatStateUsing
        $content = preg_replace_callback(
            '/->enum\(\s*\[([\s\S]*?)\]\s*\)/',
            function ($matches) {
                $options = $matches[1];

                return "->formatStateUsing(fn (\$state) => match(\$state) {\n                    ".trim($options).",\n                    default => \$state,\n                })";
            },
            $content
        );

        // Convert Filament return types to Laravilt types
        // string|UnitEnum|null → ?string (used in getNavigationGroup, getNavigationBadgeColor, etc.)
        $content = preg_replace(
            '/:\s*string\s*\|\s*UnitEnum\s*\|\s*null/',
            ': ?string',
            $content
        );

        // string|array|null → ?string (used in some navigation methods)
        $content = preg_replace(
            '/:\s*string\s*\|\s*array\s*\|\s*null/',
            ': ?string',
            $content
        );

        // int|string|null → ?int (used in getNavigationSort)
        $content = preg_replace(
            '/:\s*int\s*\|\s*string\s*\|\s*null/',
            ': ?int',
            $content
        );

        // Remove use UnitEnum; import (Filament-specific)
        $content = preg_replace('/use\s+UnitEnum\s*;\s*\n?/', '', $content);

        // Remove use BackedEnum; import if present (Filament-specific)
        $content = preg_replace('/use\s+BackedEnum\s*;\s*\n?/', '', $content);

        return $content;
    }

    protected function convertNamespace(string $content, string $relativePath, string $panel, string $fileType): string
    {
        // Find and replace namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $oldNamespace = $matches[1];
            $newNamespace = $this->buildNewNamespace($oldNamespace, $panel, $fileType);

            // For resource files, add the resource folder based on class name
            if ($fileType === 'resource' || $fileType === 'nested_resource') {
                // Get resource name from class definition
                if (preg_match('/class\s+(\w+)Resource\s+extends/', $content, $classMatch)) {
                    $resourceFolder = $classMatch[1];
                    // Add the resource folder if not already in namespace
                    if (! Str::endsWith($newNamespace, $resourceFolder)) {
                        $newNamespace .= '\\'.$resourceFolder;
                    }
                }
            }

            $content = str_replace("namespace {$oldNamespace};", "namespace {$newNamespace};", $content);
        }

        return $content;
    }

    protected function buildNewNamespace(string $oldNamespace, string $panel, string $fileType = 'other'): string
    {
        // App\Filament\Resources → App\Laravilt\Admin\Resources\Customer (for CustomerResource)
        // App\Filament\Resources\CustomerResource\Pages → App\Laravilt\Admin\Resources\Customer\Pages
        $namespace = $oldNamespace;

        // Replace Filament with Laravilt\{Panel}
        $namespace = preg_replace('/^App\\\\Filament/', "App\\Laravilt\\{$panel}", $namespace);

        // Singularize resource folders
        $parts = explode('\\', $namespace);
        $newParts = [];
        $inResources = false;
        $resourceName = null;
        $hasTypeFolder = false;
        $preservedFolders = ['Pages', 'Resources', 'RelationManagers', 'Form', 'Table', 'Infolist'];

        foreach ($parts as $i => $part) {
            if ($part === 'Resources') {
                $inResources = true;
                $newParts[] = $part;

                continue;
            }

            // Handle CustomerResource folder → Customer
            if ($inResources && Str::endsWith($part, 'Resource') && ! in_array($part, $preservedFolders)) {
                $resourceName = preg_replace('/Resource$/', '', $part);
                $newParts[] = $resourceName;

                continue;
            }

            // Handle Schemas folder - replace with Form or Infolist based on file type
            if ($part === 'Schemas') {
                // Add the correct subfolder based on file type
                $typeFolder = match ($fileType) {
                    'form' => 'Form',
                    'infolist' => 'Infolist',
                    default => 'Form', // Default to Form if we can't determine
                };
                $newParts[] = $typeFolder;
                $hasTypeFolder = true;

                continue;
            }

            // Convert Tables to Table
            if ($part === 'Tables') {
                $newParts[] = 'Table';
                $hasTypeFolder = true;

                continue;
            }

            // Track if we have a type folder
            if (in_array($part, ['Pages', 'Form', 'Table', 'Infolist', 'RelationManagers'])) {
                $hasTypeFolder = true;
                $newParts[] = $part;

                continue;
            }

            // Singularize resource folder names (but not preserved folders)
            if ($inResources && Str::endsWith($part, 's') && ! in_array($part, $preservedFolders)) {
                $singular = Str::singular($part);
                if (strlen($singular) > 2 && $singular !== $part) {
                    $newParts[] = $singular;

                    continue;
                }
            }

            $newParts[] = $part;
        }

        // For resource files, add the resource folder name
        if ($fileType === 'resource' || $fileType === 'nested_resource') {
            // If we have a resourceName from the path but it's not in the namespace parts,
            // we're dealing with a resource file directly under Resources/
            // In that case, the namespace should include the resource folder
            // This is handled by the class name in the file itself
        }

        // Add type folder to namespace if missing
        if (! $hasTypeFolder && $fileType !== 'resource' && $fileType !== 'nested_resource') {
            $typeFolder = match ($fileType) {
                'form' => 'Form',
                'table' => 'Table',
                'infolist' => 'Infolist',
                'page' => 'Pages',
                'relation_manager' => 'RelationManagers',
                default => null,
            };

            if ($typeFolder) {
                $newParts[] = $typeFolder;
            }
        }

        return implode('\\', $newParts);
    }

    protected function convertUseStatements(string $content): string
    {
        // Sort by key length descending to replace longer matches first
        $sorted = $this->namespaceMap;
        uksort($sorted, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($sorted as $from => $to) {
            // Handle empty mappings (remove the use statement)
            if ($to === '') {
                $content = preg_replace('/use\s+'.preg_quote($from, '/').';\s*\n?/', '', $content);

                continue;
            }

            // Handle wildcard replacements (ending with \)
            if (Str::endsWith($from, '\\')) {
                $pattern = '/use\s+'.preg_quote(rtrim($from, '\\'), '/').'\\\\([^;]+);/';
                $content = preg_replace_callback($pattern, function ($matches) use ($to) {
                    return 'use '.rtrim($to, '\\').'\\'.$matches[1].';';
                }, $content);
            } else {
                $content = str_replace("use {$from};", "use {$to};", $content);
                $content = str_replace("use {$from} ", "use {$to} ", $content);
            }
        }

        // Remove Heroicon import
        $content = preg_replace('/use\s+Filament\\\\Support\\\\Icons\\\\Heroicon;\s*\n?/', '', $content);

        // Remove BackedEnum import if only used for icons
        $content = preg_replace('/use\s+BackedEnum;\s*\n?/', '', $content);

        // Remove RVxLab imports (ColorPicker package)
        $content = preg_replace('/use\s+RVxLab\\\\[^;]+;\s*\n?/', '', $content);

        // Remove Mohamedsabil83 imports (TinyEditor package)
        $content = preg_replace('/use\s+Mohamedsabil83\\\\[^;]+;\s*\n?/', '', $content);

        // Remove FilamentTiptapEditor imports
        $content = preg_replace('/use\s+FilamentTiptapEditor\\\\[^;]+;\s*\n?/', '', $content);

        // Remove Closure import if not used
        if (! preg_match('/\bClosure\s+\$|\(\s*Closure\s*\)/', $content)) {
            $content = preg_replace('/use\s+Closure;\s*\n?/', '', $content);
        }

        // Convert inline class prefixes: Forms\Components\* → Laravilt components
        // Handle specific Schemas components first (Tabs, Grid, Section, Card, etc.)
        $schemasComponents = [
            // Utilities - must come before catch-all patterns
            'Forms\\Get' => 'Laravilt\\Support\\Utilities\\Get',
            'Forms\\Set' => 'Laravilt\\Support\\Utilities\\Set',
            'Forms\\Components\\Card' => 'Laravilt\\Schemas\\Components\\Section',
            'Forms\\Components\\Tabs\\Tab' => 'Laravilt\\Schemas\\Components\\Tabs\\Tab',
            'Forms\\Components\\Tabs' => 'Laravilt\\Schemas\\Components\\Tabs',
            'Forms\\Components\\Grid' => 'Laravilt\\Schemas\\Components\\Grid',
            'Forms\\Components\\Section' => 'Laravilt\\Schemas\\Components\\Section',
            'Forms\\Components\\Fieldset' => 'Laravilt\\Schemas\\Components\\Fieldset',
            'Forms\\Components\\Wizard\\Step' => 'Laravilt\\Schemas\\Components\\Wizard\\Step',
            'Forms\\Components\\Wizard' => 'Laravilt\\Schemas\\Components\\Wizard',
            'Forms\\Components\\BelongsToSelect' => 'Laravilt\\Forms\\Components\\Select',
            'Forms\\Components\\BelongsToManyMultiSelect' => 'Laravilt\\Forms\\Components\\Select',
        ];

        // Sort by key length descending to replace longer matches first
        uksort($schemasComponents, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($schemasComponents as $from => $to) {
            $content = preg_replace(
                '/(?<!\\\\)'.preg_quote($from, '/').'(?=::|\s|;|,|\)|\]|$)/',
                '\\'.$to,
                $content
            );
        }

        // Handle Tables inline conversions
        $tableConversions = [
            'Tables\\Columns\\BadgeColumn' => 'Laravilt\\Tables\\Columns\\BadgeColumn',
            'Tables\\Columns\\' => 'Laravilt\\Tables\\Columns\\',
            'Tables\\Filters\\' => 'Laravilt\\Tables\\Filters\\',
        ];

        foreach ($tableConversions as $prefix => $replacement) {
            if (str_ends_with($prefix, '\\')) {
                // Wildcard conversion
                $content = preg_replace_callback(
                    '/(?<!\\\\)'.preg_quote($prefix, '/').'([\w\\\\]+)/',
                    function ($matches) use ($replacement) {
                        return '\\'.$replacement.$matches[1];
                    },
                    $content
                );
            } else {
                // Exact match
                $content = preg_replace(
                    '/(?<!\\\\)'.preg_quote($prefix, '/').'(?=::|\s|;|,|\)|\]|$)/',
                    '\\'.$replacement,
                    $content
                );
            }
        }

        // Handle remaining Forms inline conversions (after specific ones are done)
        $content = preg_replace_callback(
            '/(?<!\\\\)Forms\\\\Components\\\\([\w\\\\]+)/',
            function ($matches) {
                return '\\Laravilt\\Forms\\Components\\'.$matches[1];
            },
            $content
        );

        // Handle Schemas inline conversions (from `use Filament\Schemas;`)
        $schemasInlineConversions = [
            // Utilities - must come before catch-all patterns
            'Schemas\\Components\\Utilities\\Get' => 'Laravilt\\Support\\Utilities\\Get',
            'Schemas\\Components\\Utilities\\Set' => 'Laravilt\\Support\\Utilities\\Set',
            'Schemas\\Components\\Section' => 'Laravilt\\Schemas\\Components\\Section',
            'Schemas\\Components\\Grid' => 'Laravilt\\Schemas\\Components\\Grid',
            'Schemas\\Components\\Tabs\\Tab' => 'Laravilt\\Schemas\\Components\\Tabs\\Tab',
            'Schemas\\Components\\Tabs' => 'Laravilt\\Schemas\\Components\\Tabs',
            'Schemas\\Components\\Wizard\\Step' => 'Laravilt\\Schemas\\Components\\Wizard\\Step',
            'Schemas\\Components\\Wizard' => 'Laravilt\\Schemas\\Components\\Wizard',
            'Schemas\\Components\\Fieldset' => 'Laravilt\\Schemas\\Components\\Fieldset',
            'Schemas\\Components\\' => 'Laravilt\\Schemas\\Components\\',
        ];

        uksort($schemasInlineConversions, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($schemasInlineConversions as $prefix => $replacement) {
            if (str_ends_with($prefix, '\\')) {
                $content = preg_replace_callback(
                    '/(?<!\\\\)'.preg_quote($prefix, '/').'([\w\\\\]+)/',
                    function ($matches) use ($replacement) {
                        return '\\'.$replacement.$matches[1];
                    },
                    $content
                );
            } else {
                $content = preg_replace(
                    '/(?<!\\\\)'.preg_quote($prefix, '/').'(?=::|\s|;|,|\)|\]|$)/',
                    '\\'.$replacement,
                    $content
                );
            }
        }

        // Handle Infolists Components inline conversions (from `use Filament\Infolists\Components;`)
        // Convert Components\TextEntry, Components\ImageEntry, etc.
        $infolistComponentsMap = [
            'Components\\TextEntry' => 'Laravilt\\Infolists\\Entries\\TextEntry',
            'Components\\ImageEntry' => 'Laravilt\\Infolists\\Entries\\ImageEntry',
            'Components\\IconEntry' => 'Laravilt\\Infolists\\Entries\\IconEntry',
            'Components\\ColorEntry' => 'Laravilt\\Infolists\\Entries\\ColorEntry',
            'Components\\BooleanEntry' => 'Laravilt\\Infolists\\Entries\\BooleanEntry',
            'Components\\Section' => 'Laravilt\\Schemas\\Components\\Section',
            'Components\\Grid' => 'Laravilt\\Schemas\\Components\\Grid',
        ];

        foreach ($infolistComponentsMap as $from => $to) {
            $content = preg_replace(
                '/(?<!\\\\|\\w)'.preg_quote($from, '/').'(?=::|\s|;|,|\)|\]|$)/',
                '\\'.$to,
                $content
            );
        }

        // Handle Actions inline conversions (from `use Filament\Actions;`)
        $actionsInlineMap = [
            'Actions\\Action' => 'Laravilt\\Actions\\Action',
            'Actions\\EditAction' => 'Laravilt\\Actions\\EditAction',
            'Actions\\DeleteAction' => 'Laravilt\\Actions\\DeleteAction',
            'Actions\\ViewAction' => 'Laravilt\\Actions\\ViewAction',
            'Actions\\CreateAction' => 'Laravilt\\Actions\\CreateAction',
            'Actions\\BulkAction' => 'Laravilt\\Actions\\BulkAction',
            'Actions\\BulkActionGroup' => 'Laravilt\\Actions\\BulkActionGroup',
            'Actions\\DeleteBulkAction' => 'Laravilt\\Actions\\DeleteBulkAction',
        ];

        foreach ($actionsInlineMap as $from => $to) {
            $content = preg_replace(
                '/(?<!\\\\|\\w)'.preg_quote($from, '/').'(?=::|\s|;|,|\)|\]|$)/',
                '\\'.$to,
                $content
            );
        }

        // Remove incorrect Get/Set imports (should use Support\Utilities, not Schemas\Components\Utilities)
        $content = preg_replace('/use\s+Laravilt\\\\Schemas\\\\Components\\\\Utilities\\\\Get;\s*\n?/', '', $content);
        $content = preg_replace('/use\s+Laravilt\\\\Schemas\\\\Components\\\\Utilities\\\\Set;\s*\n?/', '', $content);

        // Remove duplicate use statements
        $content = $this->removeDuplicateUseStatements($content);

        return $content;
    }

    /**
     * Remove duplicate use statements from the content.
     */
    protected function removeDuplicateUseStatements(string $content): string
    {
        // Extract all use statements
        preg_match_all('/^use\s+[^;]+;$/m', $content, $matches);

        if (empty($matches[0])) {
            return $content;
        }

        $useStatements = $matches[0];
        $uniqueStatements = array_unique($useStatements);

        // Find duplicates
        $duplicates = array_diff_key($useStatements, $uniqueStatements);

        // Remove duplicates (keep the first occurrence)
        foreach ($duplicates as $duplicate) {
            // Remove only the duplicate line (not the first occurrence)
            $pattern = '/^'.preg_quote($duplicate, '/').'\n?/m';
            $content = preg_replace($pattern, '', $content, 1);
        }

        // Clean up any multiple blank lines created
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return $content;
    }

    protected function convertIcons(string $content): string
    {
        // Convert Heroicon:: enum references
        foreach ($this->iconMap as $heroicon => $lucideIcon) {
            $content = str_replace($heroicon, "'{$lucideIcon}'", $content);
        }

        // Handle any remaining Heroicon:: references with a default
        $content = preg_replace('/Heroicon::\w+/', "'layers'", $content);

        // Convert heroicon string format: 'heroicon-o-*' or 'heroicon-s-*'
        $heroiconStringMap = [
            'currency-dollar' => 'dollar-sign',
            'users' => 'users',
            'user' => 'user',
            'home' => 'home',
            'cog' => 'settings',
            'cog-6-tooth' => 'settings',
            'document' => 'file',
            'document-text' => 'file-text',
            'folder' => 'folder',
            'inbox' => 'inbox',
            'mail' => 'mail',
            'chat-bubble-left' => 'message-circle',
            'chat-bubble-left-right' => 'messages-square',
            'bell' => 'bell',
            'calendar' => 'calendar',
            'clock' => 'clock',
            'check' => 'check',
            'check-circle' => 'check-circle',
            'x-mark' => 'x',
            'x-circle' => 'x-circle',
            'exclamation-triangle' => 'alert-triangle',
            'exclamation-circle' => 'alert-circle',
            'information-circle' => 'info',
            'question-mark-circle' => 'help-circle',
            'plus' => 'plus',
            'minus' => 'minus',
            'pencil' => 'pencil',
            'trash' => 'trash-2',
            'eye' => 'eye',
            'eye-slash' => 'eye-off',
            'arrow-left' => 'arrow-left',
            'arrow-right' => 'arrow-right',
            'arrow-up' => 'arrow-up',
            'arrow-down' => 'arrow-down',
            'chevron-left' => 'chevron-left',
            'chevron-right' => 'chevron-right',
            'chevron-up' => 'chevron-up',
            'chevron-down' => 'chevron-down',
            'magnifying-glass' => 'search',
            'funnel' => 'filter',
            'adjustments-horizontal' => 'sliders-horizontal',
            'adjustments-vertical' => 'sliders',
            'bars-3' => 'menu',
            'ellipsis-vertical' => 'more-vertical',
            'ellipsis-horizontal' => 'more-horizontal',
            'link' => 'link',
            'photo' => 'image',
            'camera' => 'camera',
            'video-camera' => 'video',
            'microphone' => 'mic',
            'speaker-wave' => 'volume-2',
            'heart' => 'heart',
            'star' => 'star',
            'bookmark' => 'bookmark',
            'tag' => 'tag',
            'flag' => 'flag',
            'map-pin' => 'map-pin',
            'globe-alt' => 'globe',
            'building-office' => 'building',
            'building-storefront' => 'store',
            'shopping-cart' => 'shopping-cart',
            'shopping-bag' => 'shopping-bag',
            'credit-card' => 'credit-card',
            'banknotes' => 'banknote',
            'receipt-percent' => 'percent',
            'chart-bar' => 'bar-chart',
            'chart-pie' => 'pie-chart',
            'presentation-chart-line' => 'line-chart',
            'table-cells' => 'table',
            'list-bullet' => 'list',
            'queue-list' => 'list-ordered',
            'clipboard' => 'clipboard',
            'clipboard-document' => 'clipboard-copy',
            'archive-box' => 'archive',
            'server' => 'server',
            'cpu-chip' => 'cpu',
            'device-phone-mobile' => 'smartphone',
            'computer-desktop' => 'monitor',
            'printer' => 'printer',
            'wifi' => 'wifi',
            'signal' => 'signal',
            'key' => 'key',
            'lock-closed' => 'lock',
            'lock-open' => 'unlock',
            'shield-check' => 'shield-check',
            'finger-print' => 'fingerprint',
            'identification' => 'id-card',
            'user-group' => 'users',
            'user-plus' => 'user-plus',
            'user-minus' => 'user-minus',
            'rectangle-stack' => 'layers',
            'squares-2x2' => 'grid',
            'view-columns' => 'columns',
            'newspaper' => 'newspaper',
            'book-open' => 'book-open',
            'academic-cap' => 'graduation-cap',
            'beaker' => 'flask-conical',
            'wrench' => 'wrench',
            'wrench-screwdriver' => 'settings',
            'puzzle-piece' => 'puzzle',
            'cube' => 'box',
            'gift' => 'gift',
            'sparkles' => 'sparkles',
            'fire' => 'flame',
            'bolt' => 'zap',
            'light-bulb' => 'lightbulb',
            'sun' => 'sun',
            'moon' => 'moon',
            'cloud' => 'cloud',
            'paper-airplane' => 'send',
            'rocket-launch' => 'rocket',
            'truck' => 'truck',
            'paper-clip' => 'paperclip',
            'at-symbol' => 'at-sign',
            'hashtag' => 'hash',
            'code-bracket' => 'code',
            'command-line' => 'terminal',
            'variable' => 'variable',
        ];

        // Convert 'heroicon-o-*' and 'heroicon-s-*' strings
        $content = preg_replace_callback(
            "/['\"]heroicon-[os]-([a-z0-9-]+)['\"]/",
            function ($matches) use ($heroiconStringMap) {
                $iconName = $matches[1];
                $lucideIcon = $heroiconStringMap[$iconName] ?? 'circle';

                return "'{$lucideIcon}'";
            },
            $content
        );

        return $content;
    }

    protected function convertBackedEnum(string $content): string
    {
        // Convert: protected static string|BackedEnum|null $navigationIcon
        // To: protected static ?string $navigationIcon
        $content = preg_replace(
            '/protected\s+static\s+string\|BackedEnum\|null\s+\$navigationIcon/',
            'protected static ?string $navigationIcon',
            $content
        );

        return $content;
    }

    protected function fixClassReferences(string $content, string $panel): string
    {
        // Fix resource class references in pages
        // App\Filament\Resources\CustomerResource → App\Laravilt\Admin\Resources\Customer\CustomerResource
        // App\Filament\Resources\CustomerResource\Pages → App\Laravilt\Admin\Resources\Customer\Pages
        $content = preg_replace_callback(
            '/App\\\\Filament\\\\Resources\\\\([^:;]+)/',
            function ($matches) use ($panel) {
                $path = $matches[1];
                $parts = explode('\\', $path);

                // The last part is the class name - don't singularize it
                $className = array_pop($parts);

                $newParts = [];
                $resourceFolder = null;

                foreach ($parts as $part) {
                    // Handle Schemas folder - convert to Form or Infolist based on class name
                    if ($part === 'Schemas') {
                        continue; // Skip, we'll determine the folder from the class name
                    }

                    // Convert Tables to Table
                    if ($part === 'Tables') {
                        $newParts[] = 'Table';

                        continue;
                    }

                    // Handle CustomerResource folder → Customer
                    if (Str::endsWith($part, 'Resource')) {
                        $resourceFolder = preg_replace('/Resource$/', '', $part);
                        $newParts[] = $resourceFolder;

                        continue;
                    }

                    // Singularize resource folder names (but NOT class names)
                    if (Str::endsWith($part, 's') && ! in_array($part, ['Pages', 'Resources', 'RelationManagers'])) {
                        $singular = Str::singular($part);
                        if (strlen($singular) > 2 && $singular !== $part) {
                            $newParts[] = $singular;

                            continue;
                        }
                    }

                    $newParts[] = $part;
                }

                // Determine if we need to add Form or Infolist folder based on class name
                if ($className && ! in_array($className, ['Pages', 'Resources', 'RelationManagers'])) {
                    // Check if this looks like a Form or Infolist class
                    if (Str::endsWith($className, 'Form')) {
                        $newParts[] = 'Form';
                    } elseif (Str::endsWith($className, 'Infolist')) {
                        $newParts[] = 'Infolist';
                    } elseif (Str::endsWith($className, 'Resource')) {
                        // This is a direct reference to a Resource class
                        // Add the resource folder (Resource without the suffix)
                        $folderName = preg_replace('/Resource$/', '', $className);
                        if (! in_array($folderName, $newParts)) {
                            $newParts[] = $folderName;
                        }
                    }
                }

                // Add the class name back (unchanged)
                $newParts[] = $className;

                return "App\\Laravilt\\{$panel}\\Resources\\".implode('\\', $newParts);
            },
            $content
        );

        // Also fix Schema\ references that might be direct (not fully qualified)
        $content = preg_replace('/\\\\Schemas\\\\(\w+Form)/', '\\Form\\\\$1', $content);
        $content = preg_replace('/\\\\Schemas\\\\(\w+Infolist)/', '\\Infolist\\\\$1', $content);

        return $content;
    }
}
