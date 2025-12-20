<?php

declare(strict_types=1);

namespace Laravilt\Panel\Commands\Concerns;

use Illuminate\Support\Str;

/**
 * Methods for generating pages and relation managers.
 */
trait GeneratePages
{
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
}
