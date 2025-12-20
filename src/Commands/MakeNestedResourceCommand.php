<?php

declare(strict_types=1);

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeNestedResourceCommand extends Command
{
    protected $signature = 'laravilt:nested
                            {name : The name of the nested resource (e.g., Tag)}
                            {--parent= : The parent resource class name (e.g., Customer)}
                            {--model= : The model class (defaults to name)}
                            {--panel=Admin : The panel name}
                            {--simple : Create a simple resource with ManageRecords}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a new nested resource within a parent resource';

    protected string $resourcesPath;

    protected string $resourcesNamespace;

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $parent = Str::studly($this->option('parent') ?? $this->askForParent());
        $model = $this->option('model') ?? $name;
        $panel = Str::studly($this->option('panel'));
        $isSimple = $this->option('simple');
        $force = $this->option('force');

        // Set up paths
        $this->resourcesPath = app_path("Laravilt/{$panel}/Resources");
        $this->resourcesNamespace = "App\\Laravilt\\{$panel}\\Resources";

        // Validate parent resource exists
        $parentResourcePath = "{$this->resourcesPath}/{$parent}/{$parent}Resource.php";
        if (! file_exists($parentResourcePath)) {
            $this->error("Parent resource {$parent}Resource does not exist at: {$parentResourcePath}");
            $this->info("Create it first with: php artisan laravilt:resource {$parent}");

            return self::FAILURE;
        }

        $this->info("Creating nested resource: {$name}Resource under {$parent}Resource");
        $this->newLine();

        // Create the nested resource structure
        // Parent/Resources/Name/NameResource.php
        $nestedPath = "{$this->resourcesPath}/{$parent}/Resources/{$name}";

        if (! is_dir($nestedPath)) {
            mkdir($nestedPath, 0755, true);
        }

        // Create the main resource file
        $this->createNestedResource($name, $parent, $model, $panel, $nestedPath, $force);

        // Create Form directory and file
        $this->createFormFile($name, $parent, $panel, $nestedPath, $force);

        // Create Table directory and file
        $this->createTableFile($name, $parent, $panel, $nestedPath, $force);

        // Create Pages
        if ($isSimple) {
            $this->createSimplePage($name, $parent, $panel, $nestedPath, $force);
        } else {
            $this->createPages($name, $parent, $panel, $nestedPath, $force);
        }

        // Update parent resource to include nested resource
        $this->updateParentResource($name, $parent, $parentResourcePath);

        $this->newLine();
        $this->info('Nested resource created successfully!');
        $this->newLine();

        $this->table(
            ['File', 'Location'],
            [
                ["{$name}Resource.php", str_replace(base_path().'/', '', "{$nestedPath}/{$name}Resource.php")],
                ["{$name}Form.php", str_replace(base_path().'/', '', "{$nestedPath}/Form/{$name}Form.php")],
                ["{$name}Table.php", str_replace(base_path().'/', '', "{$nestedPath}/Table/{$name}Table.php")],
                ...($isSimple
                    ? [["Manage{$name}s.php", str_replace(base_path().'/', '', "{$nestedPath}/Pages/Manage{$name}s.php")]]
                    : [
                        ["List{$name}s.php", str_replace(base_path().'/', '', "{$nestedPath}/Pages/List{$name}s.php")],
                        ["Create{$name}.php", str_replace(base_path().'/', '', "{$nestedPath}/Pages/Create{$name}.php")],
                        ["Edit{$name}.php", str_replace(base_path().'/', '', "{$nestedPath}/Pages/Edit{$name}.php")],
                    ]),
            ]
        );

        return self::SUCCESS;
    }

    protected function askForParent(): string
    {
        // List available resources
        $resources = [];
        if (is_dir($this->resourcesPath)) {
            foreach (scandir($this->resourcesPath) as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir("{$this->resourcesPath}/{$dir}")) {
                    if (file_exists("{$this->resourcesPath}/{$dir}/{$dir}Resource.php")) {
                        $resources[] = $dir;
                    }
                }
            }
        }

        if (empty($resources)) {
            $this->error('No parent resources found. Create a resource first with: php artisan laravilt:resource <Name>');
            exit(1);
        }

        return $this->choice('Select the parent resource', $resources);
    }

    protected function createNestedResource(string $name, string $parent, string $model, string $panel, string $path, bool $force): void
    {
        $filePath = "{$path}/{$name}Resource.php";

        if (file_exists($filePath) && ! $force) {
            $this->warn("Resource already exists: {$filePath}");

            return;
        }

        $namespace = "{$this->resourcesNamespace}\\{$parent}\\Resources\\{$name}";
        $parentNamespace = "{$this->resourcesNamespace}\\{$parent}\\{$parent}Resource";
        $modelNamespace = "App\\Models\\{$model}";

        $stub = <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace};
use {$parentNamespace};
use {$namespace}\\Form\\{$name}Form;
use {$namespace}\\Pages;
use {$namespace}\\Table\\{$name}Table;
use Laravilt\\Panel\\Resources\\NestedResource;
use Laravilt\\Schemas\\Schema;
use Laravilt\\Tables\\Table;

class {$name}Resource extends NestedResource
{
    protected static string \$model = {$model}::class;

    protected static ?string \$parentResource = {$parent}Resource::class;

    protected static string \$parentRelationship = '{$this->getParentRelationship($parent)}';

    protected static ?string \$navigationIcon = 'tag';

    public static function form(Schema \$schema): Schema
    {
        return {$name}Form::schema(\$schema);
    }

    public static function table(Table \$table): Table
    {
        return {$name}Table::table(\$table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\\List{$name}s::route('/'),
            'create' => Pages\\Create{$name}::route('/create'),
            'edit' => Pages\\Edit{$name}::route('/{record}/edit'),
        ];
    }
}

PHP;

        file_put_contents($filePath, $stub);
        $this->info("Created: {$filePath}");
    }

    protected function createFormFile(string $name, string $parent, string $panel, string $path, bool $force): void
    {
        $formPath = "{$path}/Form";
        if (! is_dir($formPath)) {
            mkdir($formPath, 0755, true);
        }

        $filePath = "{$formPath}/{$name}Form.php";

        if (file_exists($filePath) && ! $force) {
            $this->warn("Form already exists: {$filePath}");

            return;
        }

        $namespace = "{$this->resourcesNamespace}\\{$parent}\\Resources\\{$name}\\Form";

        $stub = <<<PHP
<?php

namespace {$namespace};

use Laravilt\\Forms\\Components\\TextInput;
use Laravilt\\Schemas\\Schema;

class {$name}Form
{
    public static function schema(Schema \$schema): Schema
    {
        return \$schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}

PHP;

        file_put_contents($filePath, $stub);
        $this->info("Created: {$filePath}");
    }

    protected function createTableFile(string $name, string $parent, string $panel, string $path, bool $force): void
    {
        $tablePath = "{$path}/Table";
        if (! is_dir($tablePath)) {
            mkdir($tablePath, 0755, true);
        }

        $filePath = "{$tablePath}/{$name}Table.php";

        if (file_exists($filePath) && ! $force) {
            $this->warn("Table already exists: {$filePath}");

            return;
        }

        $namespace = "{$this->resourcesNamespace}\\{$parent}\\Resources\\{$name}\\Table";

        $stub = <<<PHP
<?php

namespace {$namespace};

use Laravilt\\Actions\\DeleteAction;
use Laravilt\\Actions\\EditAction;
use Laravilt\\Actions\\ViewAction;
use Laravilt\\Tables\\Columns\\TextColumn;
use Laravilt\\Tables\\Table;

class {$name}Table
{
    public static function table(Table \$table): Table
    {
        return \$table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

PHP;

        file_put_contents($filePath, $stub);
        $this->info("Created: {$filePath}");
    }

    protected function createPages(string $name, string $parent, string $panel, string $path, bool $force): void
    {
        $pagesPath = "{$path}/Pages";
        if (! is_dir($pagesPath)) {
            mkdir($pagesPath, 0755, true);
        }

        $namespace = "{$this->resourcesNamespace}\\{$parent}\\Resources\\{$name}";
        $pagesNamespace = "{$namespace}\\Pages";

        // List Page
        $listStub = <<<PHP
<?php

namespace {$pagesNamespace};

use {$namespace}\\{$name}Resource;
use Laravilt\\Actions\\CreateAction;
use Laravilt\\Panel\\Pages\\ListRecords;

class List{$name}s extends ListRecords
{
    protected static ?string \$resource = {$name}Resource::class;

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

PHP;

        $listPath = "{$pagesPath}/List{$name}s.php";
        if (! file_exists($listPath) || $force) {
            file_put_contents($listPath, $listStub);
            $this->info("Created: {$listPath}");
        }

        // Create Page
        $createStub = <<<PHP
<?php

namespace {$pagesNamespace};

use {$namespace}\\{$name}Resource;
use Laravilt\\Panel\\Pages\\CreateRecord;

class Create{$name} extends CreateRecord
{
    protected static ?string \$resource = {$name}Resource::class;
}

PHP;

        $createPath = "{$pagesPath}/Create{$name}.php";
        if (! file_exists($createPath) || $force) {
            file_put_contents($createPath, $createStub);
            $this->info("Created: {$createPath}");
        }

        // Edit Page
        $editStub = <<<PHP
<?php

namespace {$pagesNamespace};

use {$namespace}\\{$name}Resource;
use Laravilt\\Panel\\Pages\\EditRecord;

class Edit{$name} extends EditRecord
{
    protected static ?string \$resource = {$name}Resource::class;
}

PHP;

        $editPath = "{$pagesPath}/Edit{$name}.php";
        if (! file_exists($editPath) || $force) {
            file_put_contents($editPath, $editStub);
            $this->info("Created: {$editPath}");
        }
    }

    protected function createSimplePage(string $name, string $parent, string $panel, string $path, bool $force): void
    {
        $pagesPath = "{$path}/Pages";
        if (! is_dir($pagesPath)) {
            mkdir($pagesPath, 0755, true);
        }

        $namespace = "{$this->resourcesNamespace}\\{$parent}\\Resources\\{$name}";
        $pagesNamespace = "{$namespace}\\Pages";

        $stub = <<<PHP
<?php

namespace {$pagesNamespace};

use {$namespace}\\{$name}Resource;
use Laravilt\\Actions\\CreateAction;
use Laravilt\\Panel\\Pages\\ManageRecords;

class Manage{$name}s extends ManageRecords
{
    protected static ?string \$resource = {$name}Resource::class;

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

PHP;

        $filePath = "{$pagesPath}/Manage{$name}s.php";
        if (! file_exists($filePath) || $force) {
            file_put_contents($filePath, $stub);
            $this->info("Created: {$filePath}");
        }

        // Update resource to use simple page
        $resourcePath = "{$path}/{$name}Resource.php";
        if (file_exists($resourcePath)) {
            $content = file_get_contents($resourcePath);
            $content = str_replace(
                "'index' => Pages\\List{$name}s::route('/'),\n            'create' => Pages\\Create{$name}::route('/create'),\n            'edit' => Pages\\Edit{$name}::route('/{record}/edit'),",
                "'index' => Pages\\Manage{$name}s::route('/'),",
                $content
            );
            file_put_contents($resourcePath, $content);
        }
    }

    protected function updateParentResource(string $name, string $parent, string $parentResourcePath): void
    {
        $content = file_get_contents($parentResourcePath);

        // Check if getNestedResources method exists
        if (str_contains($content, 'getNestedResources')) {
            // Add to existing array
            $pattern = '/(public\s+static\s+function\s+getNestedResources\s*\(\s*\)\s*:\s*array\s*\{\s*return\s*\[)/';
            if (preg_match($pattern, $content)) {
                $nestedResourceClass = "Resources\\{$name}\\{$name}Resource::class";
                if (! str_contains($content, $nestedResourceClass)) {
                    $content = preg_replace(
                        $pattern,
                        "$1\n            {$nestedResourceClass},",
                        $content
                    );
                    file_put_contents($parentResourcePath, $content);
                    $this->info("Updated {$parent}Resource with nested resource reference.");
                }
            }
        } else {
            // Add the method before the closing brace of the class
            $nestedResourceClass = "Resources\\{$name}\\{$name}Resource";
            $method = <<<PHP

    public static function getNestedResources(): array
    {
        return [
            {$nestedResourceClass}::class,
        ];
    }
PHP;

            // Find the last closing brace
            $lastBracePos = strrpos($content, '}');
            if ($lastBracePos !== false) {
                $content = substr($content, 0, $lastBracePos).$method."\n".substr($content, $lastBracePos);
                file_put_contents($parentResourcePath, $content);
                $this->info("Added getNestedResources() method to {$parent}Resource.");
            }
        }
    }

    protected function getParentRelationship(string $parent): string
    {
        return Str::camel($parent);
    }
}
