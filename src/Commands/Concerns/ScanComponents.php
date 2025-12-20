<?php

declare(strict_types=1);

namespace Laravilt\Panel\Commands\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Methods for scanning Filament components in the source directory.
 */
trait ScanComponents
{
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
     * Detect file type from content and path.
     */
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
}
