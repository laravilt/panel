<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;

class MakeRelationManagerCommand extends Command
{
    protected $signature = 'laravilt:relation {panel?} {resource?} {relationship?}';

    protected $description = 'Create a new relation manager for a resource';

    public function handle(): int
    {
        // Get available panels
        $panels = $this->getAvailablePanels();

        if (empty($panels)) {
            $this->components->error('No panels found. Please create a panel first.');
            return self::FAILURE;
        }

        // Get panel name
        $panel = $this->argument('panel');
        if (!$panel) {
            $panel = select(
                label: 'Select a panel:',
                options: $panels,
                default: $panels[0] ?? 'Admin'
            );
        }
        $panel = Str::studly($panel);

        // Get available resources
        $resources = $this->getAvailableResources($panel);

        if (empty($resources)) {
            $this->components->error("No resources found in panel [{$panel}]. Please create a resource first.");
            return self::FAILURE;
        }

        // Get resource name
        $resource = $this->argument('resource');
        if (!$resource) {
            $resource = select(
                label: 'Select a resource:',
                options: $resources,
                default: $resources[0] ?? null
            );
        }
        $resource = Str::studly($resource);

        // Get the model class from resource
        $modelClass = $this->getModelFromResource($panel, $resource);

        if (!$modelClass) {
            $this->components->error("Could not determine model class for resource [{$resource}].");
            return self::FAILURE;
        }

        // Get available relationships from the model
        $relationships = $this->getModelRelationships($modelClass);

        if (empty($relationships)) {
            $this->components->warn("No HasMany or BelongsToMany relationships found on model [{$modelClass}].");
            $relationship = text(
                label: 'Enter the relationship method name:',
                placeholder: 'e.g., reviews, variants, images',
                required: true
            );
        } else {
            $relationship = $this->argument('relationship');
            if (!$relationship) {
                $relationship = select(
                    label: 'Select a relationship:',
                    options: array_combine($relationships, $relationships),
                    default: $relationships[0] ?? null
                );
            }
        }

        $relationship = Str::camel($relationship);
        $relationManagerName = Str::studly($relationship) . 'RelationManager';

        // Get the related model
        $relatedModel = $this->getRelatedModel($modelClass, $relationship);
        $relatedModelName = class_basename($relatedModel ?? Str::studly(Str::singular($relationship)));

        // Ask for record title attribute
        $recordTitleAttribute = text(
            label: 'What is the title attribute for related records?',
            placeholder: 'e.g., name, title, label',
            default: $this->guessRecordTitleAttribute($relatedModel),
            required: true
        );

        // Create the relation manager directory
        $relationManagerDir = app_path("Laravilt/{$panel}/Resources/{$resource}/RelationManagers");
        File::ensureDirectoryExists($relationManagerDir);

        // Generate the relation manager file
        $this->createRelationManagerFile(
            $panel,
            $resource,
            $relationManagerName,
            $relationship,
            $relatedModelName,
            $recordTitleAttribute,
            $relationManagerDir
        );

        $this->newLine();
        $this->components->info("Relation Manager [{$relationManagerName}] created successfully!");
        $this->newLine();
        $this->components->info("File created:");
        $this->line("  app/Laravilt/{$panel}/Resources/{$resource}/RelationManagers/{$relationManagerName}.php");
        $this->newLine();
        $this->components->info("Don't forget to register the relation manager in your resource:");
        $this->line("  public static function getRelations(): array");
        $this->line("  {");
        $this->line("      return [");
        $this->line("          {$relationManagerName}::class,");
        $this->line("      ];");
        $this->line("  }");

        return self::SUCCESS;
    }

    protected function getAvailablePanels(): array
    {
        $laraviltPath = app_path('Laravilt');

        if (!File::isDirectory($laraviltPath)) {
            return [];
        }

        $directories = File::directories($laraviltPath);

        return collect($directories)
            ->map(fn ($dir) => basename($dir))
            ->values()
            ->toArray();
    }

    protected function getAvailableResources(string $panel): array
    {
        $resourcesPath = app_path("Laravilt/{$panel}/Resources");

        if (!File::isDirectory($resourcesPath)) {
            return [];
        }

        $directories = File::directories($resourcesPath);

        return collect($directories)
            ->map(fn ($dir) => basename($dir))
            ->values()
            ->toArray();
    }

    protected function getModelFromResource(string $panel, string $resource): ?string
    {
        $resourcePath = app_path("Laravilt/{$panel}/Resources/{$resource}/{$resource}Resource.php");

        if (!File::exists($resourcePath)) {
            return null;
        }

        $content = File::get($resourcePath);

        // Extract model class from protected static string $model = ModelClass::class;
        if (preg_match('/protected\s+static\s+string\s+\$model\s*=\s*([^;]+)::class/', $content, $matches)) {
            $modelClass = trim($matches[1]);

            // Check if it's a full namespace or just a class name
            if (!str_contains($modelClass, '\\')) {
                // Try to find the use statement
                if (preg_match('/use\s+([^;]+\\\\' . preg_quote($modelClass) . ')\s*;/', $content, $useMatch)) {
                    return $useMatch[1];
                }
                // Default to App\Models namespace
                return "App\\Models\\{$modelClass}";
            }

            return $modelClass;
        }

        return null;
    }

    protected function getModelRelationships(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $reflection = new \ReflectionClass($modelClass);
        $relationships = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $modelClass) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (!$returnType) {
                continue;
            }

            $typeName = $returnType->getName();

            // Check for HasMany, BelongsToMany, MorphMany, etc.
            if (in_array($typeName, [
                'Illuminate\Database\Eloquent\Relations\HasMany',
                'Illuminate\Database\Eloquent\Relations\BelongsToMany',
                'Illuminate\Database\Eloquent\Relations\MorphMany',
                'Illuminate\Database\Eloquent\Relations\HasManyThrough',
            ])) {
                $relationships[] = $method->getName();
            }
        }

        return $relationships;
    }

    protected function getRelatedModel(string $modelClass, string $relationship): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        try {
            $model = new $modelClass();
            if (method_exists($model, $relationship)) {
                $relation = $model->{$relationship}();
                return get_class($relation->getRelated());
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return null;
    }

    protected function guessRecordTitleAttribute(?string $modelClass): string
    {
        if (!$modelClass || !class_exists($modelClass)) {
            return 'name';
        }

        $model = new $modelClass();
        $fillable = $model->getFillable();

        $candidates = ['name', 'title', 'label', 'subject', 'heading', 'email'];

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $fillable)) {
                return $candidate;
            }
        }

        return 'id';
    }

    protected function createRelationManagerFile(
        string $panel,
        string $resource,
        string $relationManagerName,
        string $relationship,
        string $relatedModelName,
        string $recordTitleAttribute,
        string $dir
    ): void {
        $singularLabel = Str::title(Str::singular($relationship));
        $pluralLabel = Str::title($relationship);
        $icon = $this->guessIcon($relationship);

        // Generate columns based on common patterns
        $tableColumns = $this->generateTableColumns($relatedModelName, $recordTitleAttribute);
        $formFields = $this->generateFormFields($relatedModelName);

        $content = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$resource}\RelationManagers;

use Laravilt\Actions\CreateAction;
use Laravilt\Actions\DeleteAction;
use Laravilt\Actions\DeleteBulkAction;
use Laravilt\Actions\EditAction;
use Laravilt\Forms\Components\TextInput;
use Laravilt\Forms\Components\Textarea;
use Laravilt\Forms\Components\Toggle;
use Laravilt\Panel\Resources\RelationManagers\RelationManager;
use Laravilt\Schemas\Components\Grid;
use Laravilt\Schemas\Schema;
use Laravilt\Tables\Columns\TextColumn;
use Laravilt\Tables\Columns\ToggleColumn;
use Laravilt\Tables\Table;

class {$relationManagerName} extends RelationManager
{
    protected static string \$relationship = '{$relationship}';

    protected static ?string \$recordTitleAttribute = '{$recordTitleAttribute}';

    protected static ?string \$label = '{$singularLabel}';

    protected static ?string \$pluralLabel = '{$pluralLabel}';

    protected static ?string \$icon = '{$icon}';

    public function form(Schema \$schema): Schema
    {
        return \$schema
            ->schema([
                Grid::make(2)
                    ->columns(2)
                    ->schema([
{$formFields}
                    ]),
            ]);
    }

    public function table(Table \$table): Table
    {
        return \$table
            ->columns([
{$tableColumns}
            ])
            ->filters([
                // Add filters here
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
PHP;

        File::put("{$dir}/{$relationManagerName}.php", $content);
    }

    protected function guessIcon(string $relationship): string
    {
        $iconMap = [
            'review' => 'Star',
            'reviews' => 'Star',
            'comment' => 'MessageCircle',
            'comments' => 'MessageCircle',
            'image' => 'Image',
            'images' => 'Image',
            'photo' => 'Camera',
            'photos' => 'Camera',
            'variant' => 'Layers',
            'variants' => 'Layers',
            'option' => 'Settings',
            'options' => 'Settings',
            'attribute' => 'Tag',
            'attributes' => 'Tag',
            'tag' => 'Tag',
            'tags' => 'Tags',
            'category' => 'FolderTree',
            'categories' => 'FolderTree',
            'order' => 'ShoppingCart',
            'orders' => 'ShoppingCart',
            'item' => 'Box',
            'items' => 'Box',
            'file' => 'File',
            'files' => 'File',
            'document' => 'FileText',
            'documents' => 'FileText',
            'attachment' => 'Paperclip',
            'attachments' => 'Paperclip',
            'address' => 'MapPin',
            'addresses' => 'MapPin',
            'user' => 'Users',
            'users' => 'Users',
            'member' => 'UserCheck',
            'members' => 'UserCheck',
            'note' => 'StickyNote',
            'notes' => 'StickyNote',
            'activity' => 'Activity',
            'activities' => 'Activity',
            'log' => 'ScrollText',
            'logs' => 'ScrollText',
        ];

        $singular = Str::singular(strtolower($relationship));

        return $iconMap[$singular] ?? $iconMap[$relationship] ?? 'List';
    }

    protected function generateTableColumns(string $relatedModelName, string $recordTitleAttribute): string
    {
        $indent = '                ';
        $columns = [];

        // Add record title attribute
        $columns[] = "{$indent}TextColumn::make('{$recordTitleAttribute}')\n{$indent}    ->searchable()\n{$indent}    ->sortable(),";

        // Add common columns based on model name patterns
        $modelLower = strtolower($relatedModelName);

        if (str_contains($modelLower, 'review')) {
            $columns[] = "{$indent}TextColumn::make('rating')\n{$indent}    ->sortable(),";
            $columns[] = "{$indent}ToggleColumn::make('is_approved'),";
        } elseif (str_contains($modelLower, 'variant')) {
            $columns[] = "{$indent}TextColumn::make('sku')\n{$indent}    ->searchable(),";
            $columns[] = "{$indent}TextColumn::make('price')\n{$indent}    ->money('USD')\n{$indent}    ->sortable(),";
            $columns[] = "{$indent}TextColumn::make('stock_quantity')\n{$indent}    ->sortable(),";
            $columns[] = "{$indent}ToggleColumn::make('is_active'),";
        } elseif (str_contains($modelLower, 'image')) {
            $columns[] = "{$indent}TextColumn::make('alt'),";
            $columns[] = "{$indent}ToggleColumn::make('is_primary'),";
        }

        // Add created_at
        $columns[] = "{$indent}TextColumn::make('created_at')\n{$indent}    ->dateTime()\n{$indent}    ->sortable()\n{$indent}    ->toggleable(isToggledHiddenByDefault: true),";

        return implode("\n\n", $columns);
    }

    protected function generateFormFields(string $relatedModelName): string
    {
        $indent = '                        ';
        $fields = [];

        $modelLower = strtolower($relatedModelName);

        if (str_contains($modelLower, 'review')) {
            $fields[] = "{$indent}TextInput::make('reviewer_name')\n{$indent}    ->required()\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}TextInput::make('reviewer_email')\n{$indent}    ->email()\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}TextInput::make('rating')\n{$indent}    ->required()\n{$indent}    ->numeric()\n{$indent}    ->minValue(1)\n{$indent}    ->maxValue(5),";
            $fields[] = "{$indent}TextInput::make('title')\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}Textarea::make('body')\n{$indent}    ->required()\n{$indent}    ->columnSpanFull(),";
            $fields[] = "{$indent}Toggle::make('is_verified'),";
            $fields[] = "{$indent}Toggle::make('is_approved'),";
        } elseif (str_contains($modelLower, 'variant')) {
            $fields[] = "{$indent}TextInput::make('name')\n{$indent}    ->required()\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}TextInput::make('sku')\n{$indent}    ->required()\n{$indent}    ->unique(ignoreRecord: true)\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}TextInput::make('price')\n{$indent}    ->required()\n{$indent}    ->numeric()\n{$indent}    ->prefix('\$'),";
            $fields[] = "{$indent}TextInput::make('cost')\n{$indent}    ->numeric()\n{$indent}    ->prefix('\$'),";
            $fields[] = "{$indent}TextInput::make('stock_quantity')\n{$indent}    ->required()\n{$indent}    ->numeric()\n{$indent}    ->default(0),";
            $fields[] = "{$indent}TextInput::make('color')\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}TextInput::make('size')\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}Toggle::make('is_active')\n{$indent}    ->default(true),";
        } elseif (str_contains($modelLower, 'image')) {
            $fields[] = "{$indent}TextInput::make('url')\n{$indent}    ->required()\n{$indent}    ->url()\n{$indent}    ->maxLength(2048)\n{$indent}    ->columnSpanFull(),";
            $fields[] = "{$indent}TextInput::make('alt')\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}TextInput::make('title')\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}Toggle::make('is_primary'),";
            $fields[] = "{$indent}TextInput::make('sort_order')\n{$indent}    ->numeric()\n{$indent}    ->default(0),";
        } else {
            // Default fields
            $fields[] = "{$indent}TextInput::make('name')\n{$indent}    ->required()\n{$indent}    ->maxLength(255),";
            $fields[] = "{$indent}Textarea::make('description')\n{$indent}    ->columnSpanFull(),";
        }

        return implode("\n\n", $fields);
    }
}
