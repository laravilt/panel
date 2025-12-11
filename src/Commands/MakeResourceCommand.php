<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeResourceCommand extends Command
{
    protected $signature = 'laravilt:resource {panel?} {--table=} {--model=} {--simple}';

    protected $description = 'Create a new panel resource from database table with CRUD pages';

    protected array $excludedColumns = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
        'email_verified_at', 'password', 'two_factor_secret', 'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected array $columnTypeMap = [
        'string' => 'TextInput',
        'text' => 'Textarea',
        'integer' => 'TextInput',
        'bigint' => 'TextInput',
        'smallint' => 'TextInput',
        'tinyint' => 'TextInput',
        'float' => 'TextInput',
        'double' => 'TextInput',
        'decimal' => 'TextInput',
        'boolean' => 'Toggle',
        'date' => 'DatePicker',
        'datetime' => 'DateTimePicker',
        'timestamp' => 'DateTimePicker',
        'time' => 'TimePicker',
        'json' => 'KeyValue',
        'enum' => 'Select',
    ];

    /**
     * Icon mapping based on table/model name keywords
     */
    protected array $iconMap = [
        // Users & Auth
        'user' => 'Users',
        'users' => 'Users',
        'customer' => 'UserCircle',
        'customers' => 'UserCircle',
        'member' => 'UserCheck',
        'members' => 'UserCheck',
        'admin' => 'UserCog',
        'admins' => 'UserCog',
        'employee' => 'BadgeCheck',
        'employees' => 'BadgeCheck',
        'staff' => 'UsersRound',
        'role' => 'Shield',
        'roles' => 'Shield',
        'permission' => 'Key',
        'permissions' => 'Key',
        'auth' => 'Lock',
        'account' => 'User',
        'accounts' => 'User',
        'profile' => 'UserCircle2',
        'profiles' => 'UserCircle2',

        // Content & Media
        'post' => 'FileText',
        'posts' => 'FileText',
        'article' => 'Newspaper',
        'articles' => 'Newspaper',
        'blog' => 'BookOpen',
        'blogs' => 'BookOpen',
        'page' => 'File',
        'pages' => 'File',
        'document' => 'FileText',
        'documents' => 'FileText',
        'media' => 'Image',
        'image' => 'Image',
        'images' => 'Image',
        'photo' => 'Camera',
        'photos' => 'Camera',
        'video' => 'Video',
        'videos' => 'Video',
        'file' => 'File',
        'files' => 'File',
        'attachment' => 'Paperclip',
        'attachments' => 'Paperclip',
        'gallery' => 'Images',

        // E-commerce
        'product' => 'Package',
        'products' => 'Package',
        'item' => 'Box',
        'items' => 'Box',
        'order' => 'ShoppingCart',
        'orders' => 'ShoppingCart',
        'cart' => 'ShoppingBag',
        'carts' => 'ShoppingBag',
        'invoice' => 'Receipt',
        'invoices' => 'Receipt',
        'payment' => 'CreditCard',
        'payments' => 'CreditCard',
        'transaction' => 'ArrowLeftRight',
        'transactions' => 'ArrowLeftRight',
        'category' => 'FolderTree',
        'categories' => 'FolderTree',
        'brand' => 'Award',
        'brands' => 'Award',
        'coupon' => 'Ticket',
        'coupons' => 'Ticket',
        'discount' => 'Percent',
        'discounts' => 'Percent',
        'shipping' => 'Truck',
        'wishlist' => 'Heart',
        'review' => 'Star',
        'reviews' => 'Star',
        'subscription' => 'Repeat',
        'subscriptions' => 'Repeat',
        'plan' => 'CreditCard',
        'plans' => 'CreditCard',

        // Communication
        'message' => 'MessageSquare',
        'messages' => 'MessageSquare',
        'comment' => 'MessageCircle',
        'comments' => 'MessageCircle',
        'notification' => 'Bell',
        'notifications' => 'Bell',
        'email' => 'Mail',
        'emails' => 'Mail',
        'chat' => 'MessagesSquare',
        'chats' => 'MessagesSquare',
        'contact' => 'Contact',
        'contacts' => 'Contact',
        'newsletter' => 'Newspaper',

        // Organization
        'company' => 'Building2',
        'companies' => 'Building2',
        'organization' => 'Building',
        'organizations' => 'Building',
        'department' => 'Network',
        'departments' => 'Network',
        'team' => 'Users',
        'teams' => 'Users',
        'branch' => 'GitBranch',
        'branches' => 'GitBranch',
        'office' => 'Landmark',
        'offices' => 'Landmark',

        // Location
        'address' => 'MapPin',
        'addresses' => 'MapPin',
        'location' => 'MapPinned',
        'locations' => 'MapPinned',
        'country' => 'Globe',
        'countries' => 'Globe',
        'city' => 'Building',
        'cities' => 'Building',
        'state' => 'Map',
        'states' => 'Map',
        'region' => 'Globe2',
        'regions' => 'Globe2',

        // Tasks & Projects
        'task' => 'CheckSquare',
        'tasks' => 'CheckSquare',
        'project' => 'FolderKanban',
        'projects' => 'FolderKanban',
        'ticket' => 'Ticket',
        'tickets' => 'Ticket',
        'issue' => 'CircleAlert',
        'issues' => 'CircleAlert',
        'milestone' => 'Flag',
        'milestones' => 'Flag',
        'sprint' => 'Zap',
        'sprints' => 'Zap',

        // Calendar & Time
        'event' => 'Calendar',
        'events' => 'Calendar',
        'appointment' => 'CalendarCheck',
        'appointments' => 'CalendarCheck',
        'booking' => 'CalendarDays',
        'bookings' => 'CalendarDays',
        'schedule' => 'Clock',
        'schedules' => 'Clock',
        'meeting' => 'Video',
        'meetings' => 'Video',

        // Settings & Config
        'setting' => 'Settings',
        'settings' => 'Settings',
        'config' => 'Cog',
        'configuration' => 'Cog',
        'option' => 'SlidersHorizontal',
        'options' => 'SlidersHorizontal',
        'preference' => 'Settings2',
        'preferences' => 'Settings2',

        // Finance
        'budget' => 'Wallet',
        'budgets' => 'Wallet',
        'expense' => 'TrendingDown',
        'expenses' => 'TrendingDown',
        'income' => 'TrendingUp',
        'revenue' => 'DollarSign',
        'tax' => 'Receipt',
        'taxes' => 'Receipt',
        'report' => 'FileBarChart',
        'reports' => 'FileBarChart',
        'analytics' => 'BarChart3',

        // Inventory
        'inventory' => 'Package',
        'stock' => 'Boxes',
        'warehouse' => 'Warehouse',
        'supplier' => 'Truck',
        'suppliers' => 'Truck',
        'vendor' => 'Store',
        'vendors' => 'Store',

        // Support
        'faq' => 'HelpCircle',
        'faqs' => 'HelpCircle',
        'help' => 'LifeBuoy',
        'support' => 'Headphones',

        // Misc
        'log' => 'ScrollText',
        'logs' => 'ScrollText',
        'activity' => 'Activity',
        'activities' => 'Activity',
        'audit' => 'ClipboardList',
        'tag' => 'Tag',
        'tags' => 'Tags',
        'label' => 'Tag',
        'labels' => 'Tags',
        'language' => 'Languages',
        'languages' => 'Languages',
        'translation' => 'Languages',
        'currency' => 'Coins',
        'currencies' => 'Coins',
        'note' => 'StickyNote',
        'notes' => 'StickyNote',
        'link' => 'Link',
        'links' => 'Link',
        'menu' => 'Menu',
        'menus' => 'Menu',
        'banner' => 'PanelTop',
        'banners' => 'PanelTop',
        'slider' => 'GalleryHorizontal',
        'sliders' => 'GalleryHorizontal',
        'widget' => 'LayoutGrid',
        'widgets' => 'LayoutGrid',
        'template' => 'LayoutTemplate',
        'templates' => 'LayoutTemplate',
        'theme' => 'Palette',
        'themes' => 'Palette',
    ];

    // Generator options
    protected bool $generateApi = false;

    protected bool $isSimple = false;

    protected array $apiMethods = [];

    protected array $relations = [];

    protected bool $useApiTester = false;

    /**
     * Field name patterns for intelligent input type selection
     */
    protected array $fieldPatterns = [
        // Select fields
        'status' => 'Select',
        'type' => 'Select',
        'role' => 'Select',
        'category' => 'Select',
        'priority' => 'Select',
        'visibility' => 'Select',
        'level' => 'Select',
        'gender' => 'Select',
        'currency' => 'Select',
        'language' => 'Select',
        'timezone' => 'Select',

        // Location fields (dependent selects)
        'country' => 'Select',
        'country_id' => 'Select',
        'state' => 'Select',
        'state_id' => 'Select',
        'city' => 'Select',
        'city_id' => 'Select',
        'region' => 'Select',
        'province' => 'Select',

        // Tags
        'tags' => 'TagsInput',
        'keywords' => 'TagsInput',
        'skills' => 'TagsInput',
        'labels' => 'TagsInput',
        'categories' => 'TagsInput',

        // Code editors
        'code' => 'CodeEditor',
        'html' => 'CodeEditor',
        'css' => 'CodeEditor',
        'javascript' => 'CodeEditor',
        'js' => 'CodeEditor',
        'json_content' => 'CodeEditor',
        'script' => 'CodeEditor',
        'style' => 'CodeEditor',
        'template' => 'CodeEditor',
        'source' => 'CodeEditor',
        'snippet' => 'CodeEditor',

        // Rich text
        'body' => 'RichEditor',
        'content' => 'RichEditor',
        'article' => 'RichEditor',
        'post_content' => 'RichEditor',
        'message' => 'RichEditor',
        'bio' => 'RichEditor',
        'about' => 'RichEditor',

        // Textarea
        'description' => 'Textarea',
        'summary' => 'Textarea',
        'excerpt' => 'Textarea',
        'notes' => 'Textarea',
        'comment' => 'Textarea',
        'remarks' => 'Textarea',
        'address' => 'Textarea',
        'instructions' => 'Textarea',

        // Color
        'color' => 'ColorPicker',
        'colour' => 'ColorPicker',
        'background_color' => 'ColorPicker',
        'text_color' => 'ColorPicker',
        'border_color' => 'ColorPicker',
        'primary_color' => 'ColorPicker',
        'secondary_color' => 'ColorPicker',
        'accent_color' => 'ColorPicker',
        'hex_color' => 'ColorPicker',

        // Icons
        'icon' => 'IconPicker',
        'icon_name' => 'IconPicker',
        'menu_icon' => 'IconPicker',
        'nav_icon' => 'IconPicker',

        // Key-value
        'metadata' => 'KeyValue',
        'meta' => 'KeyValue',
        'attributes' => 'KeyValue',
        'settings' => 'KeyValue',
        'options' => 'KeyValue',
        'config' => 'KeyValue',
        'properties' => 'KeyValue',
        'extra' => 'KeyValue',
        'data' => 'KeyValue',
        'params' => 'KeyValue',
        'parameters' => 'KeyValue',
    ];

    /**
     * Section groupings for form fields
     */
    protected array $sectionGroups = [
        'basic' => [
            'title' => 'Basic Information',
            'icon' => 'info',
            'fields' => ['name', 'title', 'label', 'email', 'phone', 'mobile', 'username', 'slug', 'subject', 'heading'],
        ],
        'content' => [
            'title' => 'Content',
            'icon' => 'FileText',
            'fields' => ['body', 'content', 'description', 'summary', 'excerpt', 'bio', 'about', 'notes', 'message', 'article', 'post_content'],
        ],
        'media' => [
            'title' => 'Media',
            'icon' => 'Image',
            'fields' => ['image', 'photo', 'avatar', 'logo', 'thumbnail', 'cover', 'banner', 'picture', 'file', 'attachment', 'document', 'video', 'gallery'],
        ],
        'location' => [
            'title' => 'Location',
            'icon' => 'MapPin',
            'fields' => ['address', 'city', 'state', 'country', 'region', 'province', 'postal_code', 'zip_code', 'zipcode', 'latitude', 'longitude', 'lat', 'lng', 'location'],
        ],
        'status' => [
            'title' => 'Status & Classification',
            'icon' => 'Tag',
            'fields' => ['status', 'type', 'category', 'role', 'priority', 'visibility', 'level', 'tags', 'labels', 'keywords'],
        ],
        'pricing' => [
            'title' => 'Pricing & Finance',
            'icon' => 'DollarSign',
            'fields' => ['price', 'cost', 'amount', 'total', 'subtotal', 'discount', 'tax', 'fee', 'credit_limit', 'balance', 'budget', 'revenue', 'profit', 'spent', 'currency'],
        ],
        'dates' => [
            'title' => 'Dates & Time',
            'icon' => 'Calendar',
            'fields' => ['date', 'start_date', 'end_date', 'due_date', 'birth_date', 'published_at', 'expires_at', 'scheduled_at', 'event_date', 'time', 'start_time', 'end_time', 'duration', 'last_order_at'],
        ],
        'settings' => [
            'title' => 'Settings & Configuration',
            'icon' => 'Settings',
            'fields' => ['metadata', 'meta', 'settings', 'options', 'config', 'attributes', 'properties', 'params', 'parameters', 'extra', 'data'],
        ],
        'code' => [
            'title' => 'Code & Scripts',
            'icon' => 'Code',
            'fields' => ['code', 'html', 'css', 'javascript', 'js', 'json_content', 'script', 'style', 'template', 'source', 'snippet'],
        ],
        'appearance' => [
            'title' => 'Appearance',
            'icon' => 'Palette',
            'fields' => ['color', 'colour', 'background_color', 'text_color', 'border_color', 'icon', 'icon_name', 'theme'],
        ],
        'social' => [
            'title' => 'Social & Links',
            'icon' => 'Link',
            'fields' => ['url', 'website', 'link', 'facebook', 'twitter', 'instagram', 'linkedin', 'github', 'youtube', 'social'],
        ],
        'seo' => [
            'title' => 'SEO',
            'icon' => 'Search',
            'fields' => ['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'og_image', 'canonical_url', 'robots'],
        ],
        'flags' => [
            'title' => 'Options',
            'icon' => 'ToggleLeft',
            'fields' => ['is_active', 'is_enabled', 'is_visible', 'is_featured', 'is_verified', 'is_published', 'is_approved', 'is_default', 'active', 'enabled', 'visible', 'featured', 'verified', 'published', 'approved'],
        ],
    ];

    public function handle(): int
    {
        // Get available panels from the Laravilt directory
        $panels = $this->getAvailablePanels();

        if (empty($panels)) {
            $this->components->error('No panels found. Please create a panel first.');

            return self::FAILURE;
        }

        // Get panel name
        $panel = $this->argument('panel');
        if (! $panel) {
            $panel = select(
                label: 'Select a panel:',
                options: $panels,
                default: $panels[0] ?? 'Admin'
            );
        }
        $panel = Str::studly($panel);

        // Get all tables from database
        $tables = $this->getDatabaseTables();

        if (empty($tables)) {
            $this->components->error('No tables found in the database.');

            return self::FAILURE;
        }

        // Let user select a table
        $tableName = $this->option('table');
        if (! $tableName) {
            $tableName = search(
                label: 'Select a database table to generate resource from:',
                options: fn (string $value) => strlen($value) > 0
                    ? collect($tables)->filter(fn ($table) => str_contains(strtolower($table), strtolower($value)))->values()->all()
                    : $tables,
                placeholder: 'Search for a table...',
                scroll: 15
            );
        }

        if (! in_array($tableName, $tables)) {
            $this->components->error("Table '{$tableName}' not found in database.");

            return self::FAILURE;
        }

        // Get model name from table
        $modelName = $this->option('model') ?? Str::studly(Str::singular($tableName));

        // Get table columns
        $columns = $this->getTableColumns($tableName);

        if (empty($columns)) {
            $this->components->error("No columns found in table '{$tableName}'.");

            return self::FAILURE;
        }

        // Detect foreign key relations
        $this->relations = $this->detectRelations($tableName, $columns);

        $this->components->info("Generating resource for table: {$tableName}");
        $this->components->info("Model: {$modelName}");
        $this->newLine();

        // Display columns info
        $this->components->info('Found '.count($columns).' columns:');
        foreach ($columns as $column) {
            $relationInfo = '';
            if (isset($this->relations[$column['name']])) {
                $rel = $this->relations[$column['name']];
                $relationInfo = " -> BelongsTo {$rel['model']}";
            }
            $this->line("  - {$column['name']} ({$column['type']}".($column['nullable'] ? ', nullable' : '')."){$relationInfo}");
        }
        $this->newLine();

        // Display detected relations
        if (! empty($this->relations)) {
            $this->components->info('Detected '.count($this->relations).' relations:');
            foreach ($this->relations as $column => $relation) {
                $this->line("  - {$column} -> {$relation['model']} (via {$relation['table']})");
            }
            $this->newLine();
        }

        // Ask about simple resource (--simple flag or prompt)
        $this->isSimple = $this->option('simple') || confirm(
            label: 'Generate as simple resource? (Single page with modal CRUD)',
            default: false,
            hint: 'Simple resources use a single ManageRecords page with modal forms'
        );

        // Ask about API generation
        $this->generateApi = confirm(
            label: 'Generate API endpoints?',
            default: false,
            hint: 'API provides RESTful endpoints for external access'
        );

        if ($this->generateApi) {
            $this->apiMethods = multiselect(
                label: 'Select API methods to generate:',
                options: [
                    'index' => 'GET /api/{resource} - List all records',
                    'show' => 'GET /api/{resource}/{id} - Get single record',
                    'store' => 'POST /api/{resource} - Create new record',
                    'update' => 'PUT /api/{resource}/{id} - Update record',
                    'destroy' => 'DELETE /api/{resource}/{id} - Delete record',
                ],
                default: ['index', 'show', 'store', 'update', 'destroy'],
                hint: 'Use space to select/deselect methods'
            );

            $this->useApiTester = confirm(
                label: 'Enable API Tester interface?',
                default: false,
                hint: 'Shows an interactive API testing UI in the panel for this resource'
            );
        }

        // Confirm generation
        $this->newLine();
        $this->components->info('Will generate:');
        $this->line('  - Resource, Form, Table, InfoList');
        if ($this->isSimple) {
            $this->line('  - Pages: ManageRecords (single page with modal CRUD)');
        } else {
            $this->line('  - Pages: List, Create, Edit, View');
        }
        if ($this->generateApi) {
            $this->line('  - API endpoints: '.implode(', ', $this->apiMethods));
            if ($this->useApiTester) {
                $this->line('  - API Tester interface: Enabled');
            }
        }
        $this->newLine();

        if (! confirm('Proceed with resource generation?', true)) {
            $this->components->info('Generation cancelled.');

            return self::SUCCESS;
        }

        // Create model if it doesn't exist
        $this->createModel($modelName, $tableName, $columns);

        // Create resource directory structure
        $resourceDir = app_path("Laravilt/{$panel}/Resources/{$modelName}");
        File::ensureDirectoryExists($resourceDir);
        File::ensureDirectoryExists("{$resourceDir}/Form");
        File::ensureDirectoryExists("{$resourceDir}/Table");
        File::ensureDirectoryExists("{$resourceDir}/InfoList");
        File::ensureDirectoryExists("{$resourceDir}/Pages");

        if ($this->generateApi) {
            File::ensureDirectoryExists("{$resourceDir}/Api");
        }

        // Detect soft deletes
        $hasSoftDeletes = collect($columns)->contains(fn ($col) => $col['name'] === 'deleted_at');

        // Generate all files
        $this->createResourceFile($panel, $modelName, $tableName, $resourceDir, $this->isSimple, $hasSoftDeletes);
        $this->createFormFile($panel, $modelName, $columns, $resourceDir);
        $this->createTableFile($panel, $modelName, $columns, $resourceDir, $hasSoftDeletes);
        $this->createInfoListFile($panel, $modelName, $columns, $resourceDir);
        $this->createPageFiles($panel, $modelName, $resourceDir, $this->isSimple);

        if ($this->generateApi) {
            $this->createApiFile($panel, $modelName, $columns, $resourceDir);
        }

        $this->newLine();
        $this->components->info("Resource [{$modelName}] created successfully for panel [{$panel}]!");
        $this->newLine();
        $this->components->info('Generated files:');
        $this->line("  Resource: app/Laravilt/{$panel}/Resources/{$modelName}/{$modelName}Resource.php");
        $this->line("  Form: app/Laravilt/{$panel}/Resources/{$modelName}/Form/{$modelName}Form.php");
        $this->line("  Table: app/Laravilt/{$panel}/Resources/{$modelName}/Table/{$modelName}Table.php");
        $this->line("  InfoList: app/Laravilt/{$panel}/Resources/{$modelName}/InfoList/{$modelName}InfoList.php");
        if ($this->isSimple) {
            $this->line("  Pages: Manage{$modelName} (single page with modal CRUD)");
        } else {
            $this->line('  Pages: List, Create, Edit, View');
        }
        if ($this->generateApi) {
            $this->line("  API: app/Laravilt/{$panel}/Resources/{$modelName}/Api/{$modelName}Api.php");
        }

        // Run optimize to clear and rebuild caches
        $this->newLine();
        $this->components->info('Running optimize to rebuild caches...');
        $this->call('optimize');

        return self::SUCCESS;
    }

    /**
     * Get the icon name based on table/model name
     */
    protected function getIconForModel(string $modelName, string $tableName): string
    {
        $searchTerms = [
            strtolower($modelName),
            strtolower($tableName),
            strtolower(Str::singular($tableName)),
        ];

        foreach ($searchTerms as $term) {
            // Direct match
            if (isset($this->iconMap[$term])) {
                return $this->iconMap[$term];
            }

            // Partial match
            foreach ($this->iconMap as $keyword => $icon) {
                if (str_contains($term, $keyword) || str_contains($keyword, $term)) {
                    return $icon;
                }
            }
        }

        return 'Database'; // Default fallback
    }

    /**
     * Detect foreign key relations from columns
     */
    protected function detectRelations(string $tableName, array $columns): array
    {
        $relations = [];
        $allTables = $this->getDatabaseTables();

        foreach ($columns as $column) {
            $name = $column['name'];

            // Check for _id suffix (common convention)
            if (Str::endsWith($name, '_id')) {
                $relatedTableSingular = Str::beforeLast($name, '_id');
                $relatedTablePlural = Str::plural($relatedTableSingular);

                // Check if related table exists
                if (in_array($relatedTablePlural, $allTables)) {
                    $relations[$name] = [
                        'type' => 'belongsTo',
                        'table' => $relatedTablePlural,
                        'model' => Str::studly($relatedTableSingular),
                        'foreign_key' => $name,
                        'title_column' => $this->guessRelationTitleColumn($relatedTablePlural),
                    ];
                } elseif (in_array($relatedTableSingular, $allTables)) {
                    $relations[$name] = [
                        'type' => 'belongsTo',
                        'table' => $relatedTableSingular,
                        'model' => Str::studly($relatedTableSingular),
                        'foreign_key' => $name,
                        'title_column' => $this->guessRelationTitleColumn($relatedTableSingular),
                    ];
                }
            }
        }

        return $relations;
    }

    /**
     * Guess the title column for a related table
     */
    protected function guessRelationTitleColumn(string $tableName): string
    {
        $columns = $this->getTableColumns($tableName);
        $columnNames = collect($columns)->pluck('name')->toArray();

        // Common title column names
        $candidates = ['name', 'title', 'label', 'email', 'username', 'display_name', 'full_name'];

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columnNames)) {
                return $candidate;
            }
        }

        // Return first string column
        foreach ($columns as $column) {
            if ($column['type'] === 'string' && ! in_array($column['name'], ['id', 'uuid', 'slug'])) {
                return $column['name'];
            }
        }

        return 'id';
    }

    protected function getAvailablePanels(): array
    {
        // First try to get panels from the registry
        $registry = app(\Laravilt\Panel\PanelRegistry::class);
        $registeredPanels = $registry->all();

        if (! empty($registeredPanels)) {
            return collect($registeredPanels)
                ->map(fn ($panel) => Str::studly($panel->getId()))
                ->values()
                ->toArray();
        }

        // Fallback to checking app/Laravilt directory structure
        $laraviltPath = app_path('Laravilt');

        if (! File::isDirectory($laraviltPath)) {
            return [];
        }

        $directories = File::directories($laraviltPath);

        return collect($directories)
            ->map(fn ($dir) => basename($dir))
            ->values()
            ->toArray();
    }

    protected function getDatabaseTables(): array
    {
        $tables = [];
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $results = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $tables = array_map(fn ($row) => $row->name, $results);
        } elseif ($driver === 'mysql') {
            $database = config("database.connections.{$connection}.database");
            $results = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name', [$database]);
            $tables = array_map(fn ($row) => $row->table_name ?? $row->TABLE_NAME, $results);
        } elseif ($driver === 'pgsql') {
            $results = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
            $tables = array_map(fn ($row) => $row->tablename, $results);
        }

        // Filter out Laravel system tables
        $systemTables = ['migrations', 'password_reset_tokens', 'password_resets', 'failed_jobs', 'personal_access_tokens', 'jobs', 'job_batches', 'cache', 'cache_locks', 'sessions'];

        return array_values(array_filter($tables, fn ($table) => ! in_array($table, $systemTables)));
    }

    protected function getTableColumns(string $tableName): array
    {
        $columns = [];
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $results = DB::select("PRAGMA table_info({$tableName})");
            foreach ($results as $column) {
                $columns[] = [
                    'name' => $column->name,
                    'type' => $this->normalizeColumnType($column->type),
                    'nullable' => ! $column->notnull,
                    'default' => $column->dflt_value,
                ];
            }
        } elseif ($driver === 'mysql') {
            $database = config("database.connections.{$connection}.database");
            $results = DB::select('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ORDINAL_POSITION', [$database, $tableName]);
            foreach ($results as $column) {
                $columns[] = [
                    'name' => $column->COLUMN_NAME,
                    'type' => $this->normalizeColumnType($column->DATA_TYPE),
                    'nullable' => $column->IS_NULLABLE === 'YES',
                    'default' => $column->COLUMN_DEFAULT,
                    'full_type' => $column->COLUMN_TYPE,
                ];
            }
        } elseif ($driver === 'pgsql') {
            $results = DB::select('SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position', [$tableName]);
            foreach ($results as $column) {
                $columns[] = [
                    'name' => $column->column_name,
                    'type' => $this->normalizeColumnType($column->data_type),
                    'nullable' => $column->is_nullable === 'YES',
                    'default' => $column->column_default,
                ];
            }
        }

        return $columns;
    }

    protected function normalizeColumnType(string $type): string
    {
        $type = strtolower($type);

        $typeMap = [
            'varchar' => 'string',
            'char' => 'string',
            'text' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'int' => 'integer',
            'integer' => 'integer',
            'bigint' => 'bigint',
            'smallint' => 'smallint',
            'tinyint' => 'boolean',
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'numeric' => 'decimal',
            'boolean' => 'boolean',
            'bool' => 'boolean',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'time' => 'time',
            'json' => 'json',
            'jsonb' => 'json',
            'enum' => 'enum',
        ];

        return $typeMap[$type] ?? 'string';
    }

    protected function createModel(string $modelName, string $tableName, array $columns): void
    {
        $modelPath = app_path("Models/{$modelName}.php");

        if (File::exists($modelPath)) {
            $this->components->info("Model [{$modelName}] already exists, skipping...");

            return;
        }

        $fillable = collect($columns)
            ->pluck('name')
            ->filter(fn ($name) => ! in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(fn ($name) => "        '{$name}'")
            ->implode(",\n");

        $casts = collect($columns)
            ->filter(fn ($col) => in_array($col['type'], ['boolean', 'date', 'datetime', 'timestamp', 'json', 'decimal', 'float', 'double']))
            ->map(function ($col) {
                $castType = match ($col['type']) {
                    'boolean' => 'boolean',
                    'date' => 'date',
                    'datetime', 'timestamp' => 'datetime',
                    'json' => 'array',
                    'decimal', 'float', 'double' => 'decimal:2',
                    default => 'string',
                };

                return "        '{$col['name']}' => '{$castType}'";
            })
            ->implode(",\n");

        // Generate relation methods
        $relationMethods = $this->generateModelRelations();

        $hasSoftDeletes = collect($columns)->contains(fn ($col) => $col['name'] === 'deleted_at');
        $softDeletesUse = $hasSoftDeletes ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n" : '';
        $softDeletesTrait = $hasSoftDeletes ? ', SoftDeletes' : '';

        $content = <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
{$softDeletesUse}
class {$modelName} extends Model
{
    use HasFactory{$softDeletesTrait};

    protected \$table = '{$tableName}';

    protected \$fillable = [
{$fillable},
    ];

    protected \$casts = [
{$casts},
    ];
{$relationMethods}
}
PHP;

        File::put($modelPath, $content);
        $this->components->info("Created Model: app/Models/{$modelName}.php");
    }

    /**
     * Generate model relation methods
     */
    protected function generateModelRelations(): string
    {
        if (empty($this->relations)) {
            return '';
        }

        $methods = "\n";

        foreach ($this->relations as $column => $relation) {
            $methodName = Str::camel(Str::beforeLast($column, '_id'));
            $modelClass = $relation['model'];

            $methods .= <<<PHP

    public function {$methodName}(): BelongsTo
    {
        return \$this->belongsTo({$modelClass}::class, '{$column}');
    }
PHP;
        }

        return $methods;
    }

    protected function createResourceFile(string $panel, string $modelName, string $tableName, string $dir, bool $isSimple = false, bool $hasSoftDeletes = false): void
    {
        $icon = $this->getIconForModel($modelName, $tableName);

        // Build imports and method references based on options
        $apiImport = $this->generateApi ? "use App\\Laravilt\\{$panel}\\Resources\\{$modelName}\\Api\\{$modelName}Api;\nuse Laravilt\\Tables\\ApiResource;\n" : '';

        // Soft delete imports
        $softDeleteImport = $hasSoftDeletes ? "use Illuminate\\Database\\Eloquent\\Builder;\nuse Illuminate\\Database\\Eloquent\\SoftDeletingScope;\n" : '';

        $apiMethod = $this->generateApi ? <<<PHP


    public static function api(ApiResource \$api): ApiResource
    {
        return {$modelName}Api::configure(\$api);
    }
PHP : '';

        // Soft delete method - allows editing/viewing trashed records
        $softDeleteMethod = $hasSoftDeletes ? <<<'PHP'


    /**
     * Get the Eloquent query for retrieving records.
     * This removes the SoftDeletingScope to allow accessing trashed records
     * when the TrashedFilter is active.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
PHP : '';

        // Build page imports based on simple or full resource
        if ($isSimple) {
            $pageImports = "use App\\Laravilt\\{$panel}\\Resources\\{$modelName}\\Pages\\Manage{$modelName};";
            $pagesMethod = <<<PHP
    public static function getPages(): array
    {
        return [
            'index' => Manage{$modelName}::route('/'),
        ];
    }
PHP;
        } else {
            $pageImports = <<<PHP
use App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages\Create{$modelName};
use App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages\Edit{$modelName};
use App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages\List{$modelName};
use App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages\View{$modelName};
PHP;
            $pagesMethod = <<<PHP
    public static function getPages(): array
    {
        return [
            'list' => List{$modelName}::route('/'),
            'create' => Create{$modelName}::route('/create'),
            'edit' => Edit{$modelName}::route('/{record}/edit'),
            'view' => View{$modelName}::route('/{record}'),
        ];
    }
PHP;
        }

        $content = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName};

{$apiImport}{$softDeleteImport}use App\Laravilt\\{$panel}\Resources\\{$modelName}\Form\\{$modelName}Form;
use App\Laravilt\\{$panel}\Resources\\{$modelName}\InfoList\\{$modelName}InfoList;
{$pageImports}
use App\Laravilt\\{$panel}\Resources\\{$modelName}\Table\\{$modelName}Table;
use App\Models\\{$modelName};
use Laravilt\Panel\Resources\Resource;
use Laravilt\Schemas\Schema;
use Laravilt\Tables\Table;

class {$modelName}Resource extends Resource
{
    protected static string \$model = {$modelName}::class;

    protected static ?string \$table = '{$tableName}';

    protected static ?string \$navigationIcon = '{$icon}';

    protected static ?string \$navigationGroup = null;

    protected static int \$navigationSort = 1;

    public static function form(Schema \$schema): Schema
    {
        return {$modelName}Form::configure(\$schema);
    }

    public static function table(Table \$table): Table
    {
        return {$modelName}Table::configure(\$table);
    }

    public static function infolist(Schema \$schema): Schema
    {
        return {$modelName}InfoList::configure(\$schema);
    }{$apiMethod}{$softDeleteMethod}

{$pagesMethod}

    public static function getRelations(): array
    {
        return [
            // Add relation managers here
        ];
    }
}
PHP;

        File::put("{$dir}/{$modelName}Resource.php", $content);
    }

    protected function createFormFile(string $panel, string $modelName, array $columns, string $dir): void
    {
        $imports = $this->generateFormImports($columns);
        $sectionsCode = $this->generateFormSections($columns);

        $content = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Form;

{$imports}
use Laravilt\Schemas\Components\Grid;
use Laravilt\Schemas\Components\Section;
use Laravilt\Schemas\Schema;

class {$modelName}Form
{
    public static function configure(Schema \$form): Schema
    {
        return \$form
            ->schema([
{$sectionsCode}
            ]);
    }
}
PHP;

        File::put("{$dir}/Form/{$modelName}Form.php", $content);
    }

    /**
     * Generate form sections with grouped fields
     */
    protected function generateFormSections(array $columns): string
    {
        $groupedColumns = $this->groupColumnsBySection($columns);

        if (empty($groupedColumns)) {
            // Fallback: single section with all fields
            $formFields = $this->generateFormFields($columns);

            return <<<PHP
                Section::make('Information')
                    ->icon('info')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->columns(2)
                            ->schema([
{$formFields}
                            ]),
                    ]),
PHP;
        }

        $sections = [];
        $sectionIndent = '                ';

        foreach ($groupedColumns as $sectionKey => $sectionColumns) {
            $sectionInfo = $this->sectionGroups[$sectionKey] ?? [
                'title' => Str::title(str_replace('_', ' ', $sectionKey)),
                'icon' => 'info',
            ];

            $fields = [];
            foreach ($sectionColumns as $column) {
                $field = $this->generateFormField($column);
                if ($field) {
                    $fields[] = $field;
                }
            }

            if (empty($fields)) {
                continue;
            }

            $fieldsCode = implode("\n\n", $fields);
            $title = $sectionInfo['title'];
            $icon = $sectionInfo['icon'];

            $sections[] = <<<PHP
{$sectionIndent}Section::make('{$title}')
{$sectionIndent}    ->icon('{$icon}')
{$sectionIndent}    ->collapsible()
{$sectionIndent}    ->schema([
{$sectionIndent}        Grid::make(2)
{$sectionIndent}            ->columns(2)
{$sectionIndent}            ->schema([
{$fieldsCode}
{$sectionIndent}            ]),
{$sectionIndent}    ]),
PHP;
        }

        return implode("\n\n", $sections);
    }

    protected function createTableFile(string $panel, string $modelName, array $columns, string $dir, bool $hasSoftDeletes = false): void
    {
        $tableColumns = $this->generateTableColumns($columns);
        $tableImports = $this->generateTableImports($columns);

        // Soft delete imports
        $softDeleteImports = '';
        $softDeleteFilters = '';
        $softDeleteRecordActions = '';
        $softDeleteBulkActions = '';

        if ($hasSoftDeletes) {
            $softDeleteImports = <<<PHP
use Laravilt\Actions\ForceDeleteAction;
use Laravilt\Actions\ForceDeleteBulkAction;
use Laravilt\Actions\RestoreAction;
use Laravilt\Actions\RestoreBulkAction;
use Laravilt\Tables\Filters\TrashedFilter;
PHP;
            $softDeleteFilters = "\n                TrashedFilter::make(),";
            $softDeleteRecordActions = <<<'PHP'

                ForceDeleteAction::make(),
                RestoreAction::make(),
PHP;
            $softDeleteBulkActions = <<<'PHP'

                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
PHP;
        }

        $content = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Table;

use Laravilt\Actions\BulkActionGroup;
use Laravilt\Actions\DeleteAction;
use Laravilt\Actions\DeleteBulkAction;
use Laravilt\Actions\EditAction;
use Laravilt\Actions\ViewAction;
{$softDeleteImports}
{$tableImports}
use Laravilt\Tables\Table;

class {$modelName}Table
{
    public static function configure(Table \$table): Table
    {
        return \$table
            ->columns([
{$tableColumns}
            ])
            ->filters([{$softDeleteFilters}
                // Add filters here
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),{$softDeleteRecordActions}
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),{$softDeleteBulkActions}
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
PHP;

        File::put("{$dir}/Table/{$modelName}Table.php", $content);
    }

    protected function createInfoListFile(string $panel, string $modelName, array $columns, string $dir): void
    {
        $infoEntries = $this->generateInfoEntries($columns);

        $content = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\InfoList;

use Laravilt\Infolists\Entries\TextEntry;
use Laravilt\Schemas\Components\Grid;
use Laravilt\Schemas\Components\Section;
use Laravilt\Schemas\Schema;

class {$modelName}InfoList
{
    public static function configure(Schema \$infolist): Schema
    {
        return \$infolist
            ->schema([
                Section::make('Information')
                    ->schema([
                        Grid::make(2)
                            ->columns(2)
                            ->schema([
{$infoEntries}
                            ]),
                    ]),
            ]);
    }
}
PHP;

        File::put("{$dir}/InfoList/{$modelName}InfoList.php", $content);
    }

    protected function createApiFile(string $panel, string $modelName, array $columns, string $dir): void
    {
        $apiColumns = $this->generateApiColumns($columns);

        // Generate method flags
        $methodFlags = [];
        if (! in_array('index', $this->apiMethods)) {
            $methodFlags[] = '->disableIndex()';
        }
        if (! in_array('show', $this->apiMethods)) {
            $methodFlags[] = '->disableShow()';
        }
        if (! in_array('store', $this->apiMethods)) {
            $methodFlags[] = '->disableStore()';
        }
        if (! in_array('update', $this->apiMethods)) {
            $methodFlags[] = '->disableUpdate()';
        }
        if (! in_array('destroy', $this->apiMethods)) {
            $methodFlags[] = '->disableDestroy()';
        }

        // Add API tester option if enabled
        if ($this->useApiTester) {
            $methodFlags[] = '->useAPITester()';
        }

        $methodFlagsStr = ! empty($methodFlags) ? "\n            ".implode("\n            ", $methodFlags) : '';

        $content = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Api;

use Laravilt\Tables\ApiColumn;
use Laravilt\Tables\ApiResource;

class {$modelName}Api
{
    public static function configure(ApiResource \$api): ApiResource
    {
        return \$api
            ->columns([
{$apiColumns}
            ]){$methodFlagsStr};
    }
}
PHP;

        File::put("{$dir}/Api/{$modelName}Api.php", $content);
    }

    protected function createPageFiles(string $panel, string $modelName, string $dir, bool $isSimple = false): void
    {
        if ($isSimple) {
            // ManageRecords Page (single page with modal CRUD)
            $manageContent = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages;

use App\Laravilt\\{$panel}\Resources\\{$modelName}\\{$modelName}Resource;
use Laravilt\Panel\Pages\ManageRecords;

class Manage{$modelName} extends ManageRecords
{
    protected static ?string \$resource = {$modelName}Resource::class;
}
PHP;
            File::put("{$dir}/Pages/Manage{$modelName}.php", $manageContent);

            return;
        }

        // List Page
        $listContent = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages;

use App\Laravilt\\{$panel}\Resources\\{$modelName}\\{$modelName}Resource;
use Laravilt\Actions\CreateAction;
use Laravilt\Panel\Pages\ListRecords;

class List{$modelName} extends ListRecords
{
    protected static ?string \$resource = {$modelName}Resource::class;

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
PHP;
        File::put("{$dir}/Pages/List{$modelName}.php", $listContent);

        // Create Page
        $createContent = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages;

use App\Laravilt\\{$panel}\Resources\\{$modelName}\\{$modelName}Resource;
use Laravilt\Panel\Pages\CreateRecord;

class Create{$modelName} extends CreateRecord
{
    protected static ?string \$resource = {$modelName}Resource::class;
}
PHP;
        File::put("{$dir}/Pages/Create{$modelName}.php", $createContent);

        // Edit Page
        $editContent = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages;

use App\Laravilt\\{$panel}\Resources\\{$modelName}\\{$modelName}Resource;
use Laravilt\Actions\DeleteAction;
use Laravilt\Actions\ViewAction;
use Laravilt\Panel\Pages\EditRecord;

class Edit{$modelName} extends EditRecord
{
    protected static ?string \$resource = {$modelName}Resource::class;

    public function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
PHP;
        File::put("{$dir}/Pages/Edit{$modelName}.php", $editContent);

        // View Page
        $viewContent = <<<PHP
<?php

namespace App\Laravilt\\{$panel}\Resources\\{$modelName}\Pages;

use App\Laravilt\\{$panel}\Resources\\{$modelName}\\{$modelName}Resource;
use Laravilt\Actions\DeleteAction;
use Laravilt\Actions\EditAction;
use Laravilt\Panel\Pages\ViewRecord;

class View{$modelName} extends ViewRecord
{
    protected static ?string \$resource = {$modelName}Resource::class;

    public function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
PHP;
        File::put("{$dir}/Pages/View{$modelName}.php", $viewContent);
    }

    protected function generateFormFields(array $columns): string
    {
        $fields = [];

        foreach ($columns as $column) {
            if (in_array($column['name'], $this->excludedColumns)) {
                continue;
            }

            $field = $this->generateFormField($column);
            if ($field) {
                $fields[] = $field;
            }
        }

        return implode("\n\n", $fields);
    }

    /**
     * Detect intelligent field type based on name patterns
     */
    protected function detectFieldType(string $name, string $dbType): string
    {
        // FIRST: Check database type for specific types that should take priority
        // Boolean fields should ALWAYS be Toggle regardless of name
        if ($dbType === 'boolean') {
            return 'Toggle';
        }

        // Also check for boolean-like field names even if DB type is not boolean
        if (str_starts_with($name, 'is_') || str_starts_with($name, 'has_') || str_starts_with($name, 'can_') || str_starts_with($name, 'should_') || str_starts_with($name, 'allow_')) {
            return 'Toggle';
        }

        // Check for common boolean field names
        if (in_array($name, ['active', 'enabled', 'visible', 'featured', 'verified', 'published', 'approved', 'default', 'primary', 'public', 'private', 'locked', 'archived', 'deleted', 'confirmed', 'completed', 'paid', 'refunded', 'shipped', 'delivered', 'read', 'seen', 'processed', 'accepted', 'rejected', 'blocked', 'banned', 'suspended', 'draft', 'hidden', 'starred', 'pinned', 'highlighted'])) {
            return 'Toggle';
        }

        // Check exact matches in fieldPatterns
        if (isset($this->fieldPatterns[$name])) {
            return $this->fieldPatterns[$name];
        }

        // Check partial matches for patterns
        foreach ($this->fieldPatterns as $pattern => $component) {
            if (str_contains($name, $pattern)) {
                return $component;
            }
        }

        // Check for common field patterns
        if (str_contains($name, 'email')) {
            return 'TextInput:email';
        }
        if (str_contains($name, 'url') || str_contains($name, 'website') || str_contains($name, 'link')) {
            return 'TextInput:url';
        }
        if (str_contains($name, 'phone') || str_contains($name, 'tel') || str_contains($name, 'mobile')) {
            return 'TextInput:tel';
        }
        if (str_contains($name, 'password')) {
            return 'TextInput:password';
        }
        if (str_contains($name, 'image') || str_contains($name, 'photo') || str_contains($name, 'avatar') || str_contains($name, 'logo') || str_contains($name, 'thumbnail') || str_contains($name, 'cover') || str_contains($name, 'banner') || str_contains($name, 'picture')) {
            return 'FileUpload:image';
        }
        if (str_contains($name, 'file') || str_contains($name, 'attachment') || str_contains($name, 'document')) {
            return 'FileUpload';
        }
        if (str_contains($name, 'price') || str_contains($name, 'amount') || str_contains($name, 'cost') || str_contains($name, 'total') || str_contains($name, 'spent') || str_contains($name, 'balance') || str_contains($name, 'fee')) {
            return 'TextInput:money';
        }

        // Fall back to database type mapping
        return $this->columnTypeMap[$dbType] ?? 'TextInput';
    }

    /**
     * Get section for a field based on name
     */
    protected function getFieldSection(string $name): string
    {
        foreach ($this->sectionGroups as $sectionKey => $section) {
            foreach ($section['fields'] as $field) {
                if ($name === $field || str_contains($name, $field)) {
                    return $sectionKey;
                }
            }
        }

        return 'basic'; // default section
    }

    /**
     * Group columns by section
     */
    protected function groupColumnsBySection(array $columns): array
    {
        $sections = [];

        foreach ($columns as $column) {
            if (in_array($column['name'], $this->excludedColumns)) {
                continue;
            }

            $sectionKey = $this->getFieldSection($column['name']);
            if (! isset($sections[$sectionKey])) {
                $sections[$sectionKey] = [];
            }
            $sections[$sectionKey][] = $column;
        }

        // Sort sections by priority
        $priority = ['basic', 'content', 'media', 'location', 'status', 'pricing', 'dates', 'settings', 'code', 'appearance', 'social', 'seo', 'flags'];
        $sortedSections = [];
        foreach ($priority as $key) {
            if (isset($sections[$key]) && ! empty($sections[$key])) {
                $sortedSections[$key] = $sections[$key];
            }
        }

        return $sortedSections;
    }

    protected function generateFormField(array $column): ?string
    {
        $name = $column['name'];
        $type = $column['type'];
        $nullable = $column['nullable'];
        $indent = '                                ';

        // Check if this is a relation field
        if (isset($this->relations[$name])) {
            $relation = $this->relations[$name];
            $relationMethod = Str::camel(Str::beforeLast($name, '_id'));
            $titleColumn = $relation['title_column'];
            $required = ! $nullable ? "\n{$indent}    ->required()" : '';

            return "{$indent}Select::make('{$name}')\n{$indent}    ->relationship('{$relationMethod}', '{$titleColumn}')\n{$indent}    ->searchable()\n{$indent}    ->preload(){$required},";
        }

        // Detect intelligent field type
        $fieldType = $this->detectFieldType($name, $type);
        $required = ! $nullable ? "\n{$indent}    ->required()" : '';

        // Handle field type with modifiers (e.g., TextInput:email)
        $parts = explode(':', $fieldType);
        $component = $parts[0];
        $modifier = $parts[1] ?? null;

        switch ($component) {
            case 'Select':
                // Check for common status/type fields with predefined options
                if ($name === 'status') {
                    return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options([\n{$indent}        'active' => 'Active',\n{$indent}        'inactive' => 'Inactive',\n{$indent}        'pending' => 'Pending',\n{$indent}    ])\n{$indent}    ->default('active'),";
                } elseif ($name === 'gender') {
                    return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options([\n{$indent}        'male' => 'Male',\n{$indent}        'female' => 'Female',\n{$indent}        'other' => 'Other',\n{$indent}    ]),";
                } elseif ($name === 'priority') {
                    return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options([\n{$indent}        'low' => 'Low',\n{$indent}        'medium' => 'Medium',\n{$indent}        'high' => 'High',\n{$indent}        'urgent' => 'Urgent',\n{$indent}    ])\n{$indent}    ->default('medium'),";
                } elseif ($name === 'visibility') {
                    return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options([\n{$indent}        'public' => 'Public',\n{$indent}        'private' => 'Private',\n{$indent}        'draft' => 'Draft',\n{$indent}    ])\n{$indent}    ->default('public'),";
                } elseif (in_array($name, ['country', 'country_id'])) {
                    return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options([\n{$indent}        // Load countries from your data source\n{$indent}    ])\n{$indent}    ->searchable()\n{$indent}    ->live(),";
                } elseif (in_array($name, ['state', 'state_id', 'province'])) {
                    return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options(fn (Get \$get) => \n{$indent}        // Load states based on selected country\n{$indent}        []\n{$indent}    )\n{$indent}    ->searchable()\n{$indent}    ->live(),";
                } elseif (in_array($name, ['city', 'city_id'])) {
                    return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options(fn (Get \$get) => \n{$indent}        // Load cities based on selected state\n{$indent}        []\n{$indent}    )\n{$indent}    ->searchable(),";
                }

                return "{$indent}Select::make('{$name}'){$required}\n{$indent}    ->options([\n{$indent}        // Add options here\n{$indent}    ]),";

            case 'TagsInput':
                return "{$indent}TagsInput::make('{$name}')\n{$indent}    ->separator(',')\n{$indent}    ->splitKeys(['Tab', ',']){$required},";

            case 'CodeEditor':
                $language = $this->guessCodeLanguage($name);

                return "{$indent}CodeEditor::make('{$name}')\n{$indent}    ->language('{$language}')\n{$indent}    ->columnSpanFull(){$required},";

            case 'RichEditor':
                return "{$indent}RichEditor::make('{$name}'){$required}\n{$indent}    ->columnSpanFull(),";

            case 'Textarea':
                return "{$indent}Textarea::make('{$name}'){$required}\n{$indent}    ->rows(3)\n{$indent}    ->columnSpanFull(),";

            case 'ColorPicker':
                return "{$indent}ColorPicker::make('{$name}'){$required},";

            case 'IconPicker':
                return "{$indent}IconPicker::make('{$name}'){$required}\n{$indent}    ->searchable(),";

            case 'KeyValue':
                return "{$indent}KeyValue::make('{$name}')\n{$indent}    ->keyLabel('Key')\n{$indent}    ->valueLabel('Value')\n{$indent}    ->columnSpanFull(){$required},";

            case 'FileUpload':
                if ($modifier === 'image') {
                    return "{$indent}FileUpload::make('{$name}')\n{$indent}    ->image()\n{$indent}    ->directory('{$name}s'){$required},";
                }

                return "{$indent}FileUpload::make('{$name}')\n{$indent}    ->directory('{$name}s'){$required},";

            case 'TextInput':
                $extra = '';
                if ($modifier === 'email') {
                    $extra = "\n{$indent}    ->email()";
                } elseif ($modifier === 'url') {
                    $extra = "\n{$indent}    ->url()";
                } elseif ($modifier === 'tel') {
                    $extra = "\n{$indent}    ->tel()";
                } elseif ($modifier === 'password') {
                    $extra = "\n{$indent}    ->password()\n{$indent}    ->revealable()";
                } elseif ($modifier === 'money') {
                    $extra = "\n{$indent}    ->numeric()\n{$indent}    ->prefix('\$')";
                } elseif (in_array($type, ['integer', 'bigint', 'smallint', 'float', 'double', 'decimal'])) {
                    $extra = "\n{$indent}    ->numeric()";
                }

                return "{$indent}TextInput::make('{$name}'){$extra}{$required}\n{$indent}    ->maxLength(255),";

            case 'Toggle':
                $default = str_contains($name, 'active') || str_contains($name, 'enabled') || str_contains($name, 'verified') || str_contains($name, 'published') || str_contains($name, 'approved') ? 'true' : 'false';

                return "{$indent}Toggle::make('{$name}')\n{$indent}    ->default({$default}),";

            case 'DatePicker':
                return "{$indent}DatePicker::make('{$name}'){$required},";

            case 'DateTimePicker':
                return "{$indent}DateTimePicker::make('{$name}'){$required},";

            case 'TimePicker':
                return "{$indent}TimePicker::make('{$name}'){$required},";

            default:
                return "{$indent}TextInput::make('{$name}'){$required}\n{$indent}    ->maxLength(255),";
        }
    }

    /**
     * Guess the code language based on field name
     */
    protected function guessCodeLanguage(string $name): string
    {
        if (str_contains($name, 'html')) {
            return 'html';
        }
        if (str_contains($name, 'css') || str_contains($name, 'style')) {
            return 'css';
        }
        if (str_contains($name, 'javascript') || str_contains($name, 'js') || str_contains($name, 'script')) {
            return 'javascript';
        }
        if (str_contains($name, 'json')) {
            return 'json';
        }
        if (str_contains($name, 'php')) {
            return 'php';
        }
        if (str_contains($name, 'sql')) {
            return 'sql';
        }
        if (str_contains($name, 'xml')) {
            return 'xml';
        }
        if (str_contains($name, 'yaml') || str_contains($name, 'yml')) {
            return 'yaml';
        }
        if (str_contains($name, 'markdown') || str_contains($name, 'md')) {
            return 'markdown';
        }

        return 'plaintext';
    }

    protected function generateFormImports(array $columns): string
    {
        $imports = ['use Laravilt\Forms\Components\TextInput;'];

        $types = collect($columns)->pluck('type')->unique()->toArray();
        $names = collect($columns)
            ->pluck('name')
            ->filter(fn ($n) => ! in_array($n, $this->excludedColumns))
            ->toArray();

        // Check for relations (need Select)
        if (! empty($this->relations)) {
            $imports[] = 'use Laravilt\Forms\Components\Select;';
        }

        // Check each field for its detected type
        foreach ($columns as $column) {
            if (in_array($column['name'], $this->excludedColumns)) {
                continue;
            }

            $fieldType = $this->detectFieldType($column['name'], $column['type']);
            $component = explode(':', $fieldType)[0];

            switch ($component) {
                case 'Select':
                    $imports[] = 'use Laravilt\Forms\Components\Select;';
                    // Add Get import for dependent selects
                    if (in_array($column['name'], ['state', 'state_id', 'province', 'city', 'city_id'])) {
                        $imports[] = 'use Laravilt\Support\Utilities\Get;';
                    }
                    break;
                case 'TagsInput':
                    $imports[] = 'use Laravilt\Forms\Components\TagsInput;';
                    break;
                case 'CodeEditor':
                    $imports[] = 'use Laravilt\Forms\Components\CodeEditor;';
                    break;
                case 'RichEditor':
                    $imports[] = 'use Laravilt\Forms\Components\RichEditor;';
                    break;
                case 'Textarea':
                    $imports[] = 'use Laravilt\Forms\Components\Textarea;';
                    break;
                case 'ColorPicker':
                    $imports[] = 'use Laravilt\Forms\Components\ColorPicker;';
                    break;
                case 'IconPicker':
                    $imports[] = 'use Laravilt\Forms\Components\IconPicker;';
                    break;
                case 'KeyValue':
                    $imports[] = 'use Laravilt\Forms\Components\KeyValue;';
                    break;
                case 'FileUpload':
                    $imports[] = 'use Laravilt\Forms\Components\FileUpload;';
                    break;
                case 'Toggle':
                    $imports[] = 'use Laravilt\Forms\Components\Toggle;';
                    break;
                case 'DatePicker':
                    $imports[] = 'use Laravilt\Forms\Components\DatePicker;';
                    break;
                case 'DateTimePicker':
                    $imports[] = 'use Laravilt\Forms\Components\DateTimePicker;';
                    break;
                case 'TimePicker':
                    $imports[] = 'use Laravilt\Forms\Components\TimePicker;';
                    break;
            }
        }

        // Handle boolean type from database
        if (in_array('boolean', $types)) {
            $imports[] = 'use Laravilt\Forms\Components\Toggle;';
        }

        // Handle json type from database
        if (in_array('json', $types)) {
            $imports[] = 'use Laravilt\Forms\Components\KeyValue;';
        }

        // Handle date types from database
        if (in_array('date', $types)) {
            $imports[] = 'use Laravilt\Forms\Components\DatePicker;';
        }
        if (in_array('datetime', $types) || in_array('timestamp', $types)) {
            $imports[] = 'use Laravilt\Forms\Components\DateTimePicker;';
        }
        if (in_array('time', $types)) {
            $imports[] = 'use Laravilt\Forms\Components\TimePicker;';
        }

        // Handle text type from database
        if (in_array('text', $types)) {
            $imports[] = 'use Laravilt\Forms\Components\Textarea;';
        }

        sort($imports);

        return implode("\n", array_unique($imports));
    }

    protected function generateTableColumns(array $columns): string
    {
        $tableColumns = [];
        $indent = '                ';

        foreach ($columns as $column) {
            $name = $column['name'];
            $type = $column['type'];

            if ($name === 'deleted_at') {
                continue;
            }

            $extra = '';
            $hidden = '';
            $columnType = 'TextColumn';

            // Add sortable to most columns
            $sortable = '->sortable()';

            // Add searchable to text columns
            $searchable = in_array($name, ['name', 'email', 'title', 'description', 'subject']) ? '->searchable()' : '';

            // Check if this is a relation field
            if (isset($this->relations[$name])) {
                $relation = $this->relations[$name];
                $relationMethod = Str::camel(Str::beforeLast($name, '_id'));
                $titleColumn = $relation['title_column'];
                $tableColumns[] = "{$indent}TextColumn::make('{$relationMethod}.{$titleColumn}')\n{$indent}    ->label('".Str::title(str_replace('_', ' ', Str::beforeLast($name, '_id')))."'){$sortable},";

                continue;
            }

            // Handle image columns
            if (str_contains($name, 'image') || str_contains($name, 'photo') || str_contains($name, 'avatar') || str_contains($name, 'logo') || str_contains($name, 'thumbnail') || str_contains($name, 'cover') || str_contains($name, 'banner') || str_contains($name, 'picture')) {
                $tableColumns[] = "{$indent}ImageColumn::make('{$name}')\n{$indent}    ->circular(),";

                continue;
            }

            // Handle tags columns (array/json fields displayed as badges)
            if (in_array($name, ['tags', 'keywords', 'skills', 'labels', 'categories'])) {
                $tableColumns[] = "{$indent}TextColumn::make('{$name}')\n{$indent}    ->badge(),";

                continue;
            }

            // Handle boolean columns (is_*, has_*, can_*, etc.) - use ToggleColumn
            if ($type === 'boolean' || str_starts_with($name, 'is_') || str_starts_with($name, 'has_') || str_starts_with($name, 'can_') || in_array($name, ['active', 'enabled', 'visible', 'featured', 'verified', 'published', 'approved'])) {
                $tableColumns[] = "{$indent}ToggleColumn::make('{$name}'),";

                continue;
            }

            // Handle special column types
            if (in_array($name, ['status'])) {
                $extra = "\n{$indent}    ->badge()\n{$indent}    ->color(fn (string \$state): string => match (\$state) {\n{$indent}        'active' => 'success',\n{$indent}        'inactive' => 'danger',\n{$indent}        'pending' => 'warning',\n{$indent}        default => 'secondary',\n{$indent}    })";
            } elseif (in_array($name, ['type', 'role'])) {
                $extra = "\n{$indent}    ->badge()";
            } elseif (in_array($type, ['datetime', 'timestamp'])) {
                $extra = "\n{$indent}    ->dateTime()";
            } elseif ($type === 'date') {
                $extra = "\n{$indent}    ->date()";
            } elseif (in_array($type, ['decimal', 'float', 'double']) && (str_contains($name, 'price') || str_contains($name, 'amount') || str_contains($name, 'total') || str_contains($name, 'spent') || str_contains($name, 'cost'))) {
                $extra = "\n{$indent}    ->money('USD')";
            } elseif (str_contains($name, 'color') || str_contains($name, 'colour')) {
                $columnType = 'ColorColumn';
                $sortable = '';
            }

            // Hide some columns by default
            if (in_array($name, ['created_at', 'updated_at', 'id'])) {
                $hidden = "\n{$indent}    ->toggleable(isToggledHiddenByDefault: true)";
            }

            $tableColumns[] = "{$indent}{$columnType}::make('{$name}'){$searchable}{$sortable}{$extra}{$hidden},";
        }

        return implode("\n\n", $tableColumns);
    }

    protected function generateTableImports(array $columns): string
    {
        $imports = ['use Laravilt\Tables\Columns\TextColumn;'];

        $names = collect($columns)->pluck('name')->toArray();
        $types = collect($columns)->pluck('type')->toArray();

        // Check for image columns
        if (collect($names)->contains(fn ($n) => str_contains($n, 'image') || str_contains($n, 'photo') || str_contains($n, 'avatar') || str_contains($n, 'logo') || str_contains($n, 'thumbnail') || str_contains($n, 'cover') || str_contains($n, 'banner') || str_contains($n, 'picture'))) {
            $imports[] = 'use Laravilt\Tables\Columns\ImageColumn;';
        }

        // Check for boolean columns (by type or name pattern)
        $hasBooleanColumns = in_array('boolean', $types) ||
            collect($names)->contains(fn ($n) => str_starts_with($n, 'is_') ||
                str_starts_with($n, 'has_') ||
                str_starts_with($n, 'can_') ||
                in_array($n, ['active', 'enabled', 'visible', 'featured', 'verified', 'published', 'approved'])
            );
        if ($hasBooleanColumns) {
            $imports[] = 'use Laravilt\Tables\Columns\ToggleColumn;';
        }

        // Check for color columns
        if (collect($names)->contains(fn ($n) => str_contains($n, 'color') || str_contains($n, 'colour'))) {
            $imports[] = 'use Laravilt\Tables\Columns\ColorColumn;';
        }

        sort($imports);

        return implode("\n", array_unique($imports));
    }

    protected function generateInfoEntries(array $columns): string
    {
        $entries = [];
        $indent = '                                ';

        foreach ($columns as $column) {
            $name = $column['name'];
            $type = $column['type'];

            if (in_array($name, ['id', 'deleted_at', 'password', 'remember_token'])) {
                continue;
            }

            $extra = '';

            // Check if this is a relation field
            if (isset($this->relations[$name])) {
                $relation = $this->relations[$name];
                $relationMethod = Str::camel(Str::beforeLast($name, '_id'));
                $titleColumn = $relation['title_column'];
                $entries[] = "{$indent}TextEntry::make('{$relationMethod}.{$titleColumn}')\n{$indent}    ->label('".Str::title(str_replace('_', ' ', Str::beforeLast($name, '_id')))."'),";

                continue;
            }

            if (in_array($name, ['status', 'type', 'role'])) {
                $extra = "\n{$indent}    ->badge()";
            } elseif (in_array($type, ['datetime', 'timestamp'])) {
                $extra = "\n{$indent}    ->dateTime()";
            } elseif ($type === 'date') {
                $extra = "\n{$indent}    ->date()";
            } elseif (in_array($type, ['decimal', 'float', 'double']) && (str_contains($name, 'price') || str_contains($name, 'amount') || str_contains($name, 'total') || str_contains($name, 'spent') || str_contains($name, 'cost'))) {
                $extra = "\n{$indent}    ->money('USD')";
            }

            $entries[] = "{$indent}TextEntry::make('{$name}'){$extra},";
        }

        return implode("\n\n", $entries);
    }

    protected function generateApiColumns(array $columns): string
    {
        $apiColumns = [];
        $indent = '                ';

        // Fields that should be searchable
        $searchableFields = ['name', 'email', 'title', 'description', 'subject', 'company'];
        // Fields that should be sortable
        $sortableFields = ['id', 'name', 'email', 'created_at', 'updated_at', 'title'];
        // Fields that should be filterable
        $filterableFields = ['status', 'type', 'category', 'is_active', 'is_verified'];

        foreach ($columns as $column) {
            $name = $column['name'];
            $type = $column['type'] ?? 'string';

            if (in_array($name, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])) {
                continue;
            }

            $methods = [];

            // Add type for non-string columns
            $apiType = match ($type) {
                'integer', 'bigint', 'smallint', 'tinyint' => 'integer',
                'decimal', 'float', 'double' => 'decimal',
                'boolean' => 'boolean',
                'date' => 'date',
                'datetime', 'timestamp' => 'datetime',
                'time' => 'time',
                'json', 'array' => 'array',
                'object' => 'object',
                default => null,
            };

            if ($apiType) {
                $methods[] = "->type('{$apiType}')";
            }

            // Add searchable
            if (in_array($name, $searchableFields)) {
                $methods[] = '->searchable()';
            }

            // Add sortable
            if (in_array($name, $sortableFields)) {
                $methods[] = '->sortable()';
            }

            // Add filterable
            if (in_array($name, $filterableFields)) {
                $methods[] = '->filterable()';
            }

            $methodsStr = implode('', $methods);
            $apiColumns[] = "{$indent}ApiColumn::make('{$name}'){$methodsStr},";
        }

        return implode("\n", $apiColumns);
    }

    protected function findTitleField(array $columns): string
    {
        $titleCandidates = ['name', 'title', 'label', 'subject', 'heading'];

        foreach ($titleCandidates as $candidate) {
            if (collect($columns)->contains(fn ($col) => $col['name'] === $candidate)) {
                return $candidate;
            }
        }

        // Return first string column
        foreach ($columns as $column) {
            if ($column['type'] === 'string' && ! in_array($column['name'], $this->excludedColumns)) {
                return $column['name'];
            }
        }

        return 'id';
    }

    protected function findDescriptionField(array $columns): string
    {
        $descCandidates = ['email', 'description', 'subtitle', 'summary', 'excerpt'];

        foreach ($descCandidates as $candidate) {
            if (collect($columns)->contains(fn ($col) => $col['name'] === $candidate)) {
                return $candidate;
            }
        }

        // Return second string column or created_at
        $stringColumns = collect($columns)->filter(fn ($col) => $col['type'] === 'string' && ! in_array($col['name'], $this->excludedColumns))->values();

        if ($stringColumns->count() > 1) {
            return $stringColumns[1]['name'];
        }

        return 'created_at';
    }

    protected function findImageField(array $columns): ?string
    {
        $imageCandidates = ['image', 'photo', 'avatar', 'thumbnail', 'logo', 'picture', 'cover'];

        foreach ($columns as $column) {
            foreach ($imageCandidates as $candidate) {
                if (str_contains($column['name'], $candidate)) {
                    return $column['name'];
                }
            }
        }

        return null;
    }
}
