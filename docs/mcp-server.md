# MCP Server Integration

The Laravilt Panel package can be integrated with MCP (Model Context Protocol) server for AI agent interaction.

## Available Generator Commands

### laravilt:panel
Create a new panel with all necessary scaffolding.

**Usage:**
```bash
php artisan laravilt:panel admin
php artisan laravilt:panel tenant --path=/tenant/{tenant}
```

**Arguments:**
- `id` (string, required): Panel identifier (kebab-case recommended)

**Options:**
- `--path`: Custom URL path for the panel (defaults to `/{id}`)

**What It Generates:**
- Panel provider: `app/Providers/{StudlyId}PanelProvider.php`
- Pages directory: `app/Laravilt/{StudlyId}/Pages/`
- Resources directory: `app/Laravilt/{StudlyId}/Resources/`
- Widgets directory: `app/Laravilt/{StudlyId}/Widgets/`
- Dashboard page: `app/Laravilt/{StudlyId}/Pages/Dashboard.php`
- Vue dashboard: `resources/js/pages/{StudlyId}/Dashboard.vue`
- Auto-registers provider in `bootstrap/providers.php`

**Generated Provider Structure:**

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;use Laravilt\Panel\Panel;

class AdminPanelProvider extends ServiceProvider
{
    public function boot(): void
    {
        Panel::make('admin')
            ->path('/admin')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->pages([
                \Admin\Pages\Dashboard::class,
            ])
            ->resources([
                // Register resources here
            ])
            ->register();
    }
}
```

### laravilt:page
Create a new page for a panel.

**Usage:**
```bash
php artisan laravilt:page admin Settings
php artisan laravilt:page admin Users/Profile
```

**Arguments:**
- `panel` (string, required): Panel name (StudlyCase)
- `name` (string, required): Page name (can include subdirectories)

**What It Generates:**
- PHP Page: `app/Laravilt/{Panel}/Pages/{Name}.php`
- Vue View: `resources/js/pages/{Panel}/{Name}.vue`

**Generated Page Structure:**
```php
<?php

namespace App\Laravilt\Admin\Pages;

use Laravilt\Panel\Pages\Page;

class Settings extends Page
{
    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings';

    protected static ?string $navigationIcon = 'Settings';

    protected function getInertiaProps(): array
    {
        return [
            // Return page props here
        ];
    }
}
```

### laravilt:resource
Create a complete resource with CRUD pages from a database table.

**Usage:**
```bash
php artisan laravilt:resource
php artisan laravilt:resource admin --table=products
php artisan laravilt:resource admin --table=products --model=Product
```

**Arguments:**
- `panel` (string, optional): Panel name (interactive if not provided)

**Options:**
- `--table`: Database table to generate from
- `--model`: Custom model name (defaults to singular StudlyCase of table)

**Interactive Prompts:**
1. Select a panel (from available panels)
2. Search and select a database table
3. Review detected columns and relations
4. Choose to generate Grid view (optional)
5. Choose to generate API endpoints (optional)
6. Select API methods to generate

**What It Generates:**
- Model: `app/Models/{ModelName}.php` (if not exists)
- Resource: `app/Laravilt/{Panel}/Resources/{ModelName}/{ModelName}Resource.php`
- Form: `app/Laravilt/{Panel}/Resources/{ModelName}/Form/{ModelName}Form.php`
- Table: `app/Laravilt/{Panel}/Resources/{ModelName}/Table/{ModelName}Table.php`
- InfoList: `app/Laravilt/{Panel}/Resources/{ModelName}/InfoList/{ModelName}InfoList.php`
- Pages: List, Create, Edit, View
- Grid (optional): `app/Laravilt/{Panel}/Resources/{ModelName}/Grid/{ModelName}Grid.php`
- API (optional): `app/Laravilt/{Panel}/Resources/{ModelName}/Api/{ModelName}Api.php`

**Smart Features:**
- Auto-detects column types and generates appropriate form fields
- Detects foreign key relations (e.g., `user_id` -> `users` table)
- Generates BelongsTo relationships in models
- Maps table/model names to appropriate icons (100+ icon mappings)
- Handles JSON columns with KeyValue or Repeater components
- Recognizes special fields (email, url, phone, password, color, file)
- Generates proper validation rules
- Creates appropriate table columns with sorting/searching

## Integration Example

MCP server tools should provide:

1. **list-panels** - List all available panels
2. **panel-info** - Get details about a specific panel
3. **generate-panel** - Generate a new panel with configuration
4. **list-resources** - List all resources in a panel
5. **generate-resource** - Generate a resource from a table
6. **list-pages** - List all pages in a panel
7. **generate-page** - Generate a new page

## Panel Methods Reference

### Basic Configuration
```php
Panel::make(string $id)                  // Create panel with ID
    ->path(string $path)                  // Set URL path
    ->authGuard(string $guard)            // Set auth guard
    ->middleware(array $middleware)       // Add middleware
    ->domain(string $domain)              // Set domain
```

### Authentication Features
```php
    ->login(bool|string $page = true)     // Enable login
    ->registration(bool|string $page)     // Enable registration
    ->emailVerification()                 // Require email verification
    ->otp()                               // Enable OTP
    ->twoFactor(array $config)            // Enable 2FA
    ->passwordReset()                     // Enable password reset
    ->socialLogin(array $providers)       // Enable social auth
    ->passkeys()                          // Enable passkeys
    ->magicLinks()                        // Enable magic links
```

### Pages & Navigation
```php
    ->pages(array $pages)                 // Register pages
    ->resources(array $resources)         // Register resources
    ->navigation(array $items)            // Set navigation
    ->navigationGroups(array $groups)     // Set navigation groups
```

### Branding
```php
    ->brandName(string $name)             // Set brand name
    ->brandLogo(string $url)              // Set logo URL
    ->brandUrl(string $url)               // Set brand link
    ->favicon(string $url)                // Set favicon
```

## Resource Class Reference

### Properties
```php
protected static string $model;           // Model class
protected static ?string $table;          // Database table name
protected static ?string $navigationIcon; // Lucide icon name
protected static ?string $navigationGroup;// Navigation group
protected static int $navigationSort;     // Sort order
protected static bool $hasApi = false;    // Enable API endpoints
protected static bool $hasGrid = false;   // Enable grid view
```

### Methods
```php
public static function form(Schema $schema): Schema;
public static function table(Table $table): Table;
public static function infolist(Schema $schema): Schema;
public static function grid(Grid $grid): Grid;      // If hasGrid
public static function api(ApiResource $api): ApiResource; // If hasApi
public static function getPages(): array;
public static function getRelations(): array;
```

## Page Class Reference

### Properties
```php
protected static ?string $title;              // Page title
protected static ?string $slug;               // URL slug
protected static ?string $navigationIcon;     // Navigation icon
protected static ?string $navigationGroup;    // Navigation group
protected static ?int $navigationSort;        // Navigation sort order
protected static bool $shouldRegisterNavigation = true;
```

### Methods
```php
public function getHeading(): string;         // Page heading
public function getSubheading(): ?string;     // Page subheading
public function getLayout(): string;          // Page layout
protected function getSchema(): array;        // Form schema
protected function getActions(): array;       // Page actions
protected function getInertiaProps(): array;  // Props for frontend
public function getHeaderActions(): array;    // Header actions
```

## Icon Mapping

The resource generator includes smart icon mapping based on table/model names. Here are some examples:

| Keyword | Icon |
|---------|------|
| user, users | Users |
| customer | UserCircle |
| product | Package |
| order | ShoppingCart |
| payment | CreditCard |
| post, article | FileText |
| category | FolderTree |
| setting | Settings |
| notification | Bell |
| message | MessageSquare |
| project | FolderKanban |
| task | CheckSquare |
| event | Calendar |
| company | Building2 |
| report | FileBarChart |

## Security

The MCP server runs with the same permissions as your Laravel application. Ensure:
- Proper file permissions on the app/Laravilt directory
- Secure configuration of the MCP server
- Limited access to the MCP configuration file
- Validation of user inputs before passing to generators
