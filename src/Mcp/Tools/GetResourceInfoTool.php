<?php

namespace Laravilt\Panel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetResourceInfoTool extends Tool
{
    protected string $description = 'Get detailed information about resource structure, properties, and methods';

    protected array $resourceInfo = [
        'properties' => [
            'protected static string $model' => 'The Eloquent model class for this resource',
            'protected static ?string $table' => 'Database table name (optional)',
            'protected static ?string $navigationIcon' => 'Lucide icon name for navigation',
            'protected static ?string $navigationGroup' => 'Navigation group name',
            'protected static int $navigationSort' => 'Sort order in navigation (default: 0)',
            'protected static bool $hasApi = false' => 'Enable API endpoints',
            'protected static bool $hasGrid = false' => 'Enable grid view',
        ],
        'methods' => [
            'form(Schema $schema): Schema' => 'Define form fields for create/edit',
            'table(Table $table): Table' => 'Define table columns and filters',
            'infolist(Schema $schema): Schema' => 'Define view page layout',
            'grid(Grid $grid): Grid' => 'Define grid card layout (if hasGrid)',
            'api(ApiResource $api): ApiResource' => 'Define API endpoints (if hasApi)',
            'getPages(): array' => 'Return resource page classes',
            'getRelations(): array' => 'Return relation manager classes',
        ],
        'pages' => [
            'ListResource' => 'Table view of all records with filtering/sorting',
            'CreateResource' => 'Form for creating new records',
            'EditResource' => 'Form for editing existing records',
            'ViewResource' => 'Read-only view of a single record',
        ],
    ];

    protected array $iconMappings = [
        'user' => 'Users',
        'customer' => 'UserCircle',
        'product' => 'Package',
        'order' => 'ShoppingCart',
        'payment' => 'CreditCard',
        'post' => 'FileText',
        'article' => 'FileText',
        'category' => 'FolderTree',
        'tag' => 'Tag',
        'setting' => 'Settings',
        'notification' => 'Bell',
        'message' => 'MessageSquare',
        'comment' => 'MessageCircle',
        'project' => 'FolderKanban',
        'task' => 'CheckSquare',
        'event' => 'Calendar',
        'company' => 'Building2',
        'team' => 'Users2',
        'role' => 'Shield',
        'permission' => 'Key',
        'report' => 'FileBarChart',
        'invoice' => 'Receipt',
        'subscription' => 'CreditCard',
        'log' => 'FileCode',
        'media' => 'Image',
        'file' => 'File',
        'folder' => 'Folder',
        'email' => 'Mail',
        'phone' => 'Phone',
        'address' => 'MapPin',
        'location' => 'Navigation',
        'country' => 'Globe',
        'language' => 'Languages',
        'currency' => 'DollarSign',
        'discount' => 'Percent',
        'coupon' => 'Ticket',
        'review' => 'Star',
        'rating' => 'ThumbsUp',
        'faq' => 'HelpCircle',
        'page' => 'FileText',
        'menu' => 'Menu',
        'widget' => 'LayoutGrid',
        'block' => 'LayoutDashboard',
        'template' => 'FileCode2',
        'theme' => 'Palette',
        'plugin' => 'Puzzle',
    ];

    public function handle(Request $request): Response
    {
        $output = "📦 Laravilt Panel - Resource Structure\n\n";
        $output .= str_repeat('=', 70)."\n\n";

        $output .= "## Resource Properties\n\n";
        foreach ($this->resourceInfo['properties'] as $prop => $desc) {
            $output .= "• `{$prop}`\n  {$desc}\n\n";
        }

        $output .= "\n## Resource Methods\n\n";
        foreach ($this->resourceInfo['methods'] as $method => $desc) {
            $output .= "• `{$method}`\n  {$desc}\n\n";
        }

        $output .= "\n## Resource Pages\n\n";
        foreach ($this->resourceInfo['pages'] as $page => $desc) {
            $output .= "• **{$page}** - {$desc}\n";
        }

        $output .= "\n\n## Generated File Structure\n\n";
        $output .= "```\n";
        $output .= "app/Laravilt/{Panel}/Resources/{Model}/\n";
        $output .= "├── {Model}Resource.php      # Main resource class\n";
        $output .= "├── Form/\n";
        $output .= "│   └── {Model}Form.php      # Form schema definition\n";
        $output .= "├── Table/\n";
        $output .= "│   └── {Model}Table.php     # Table columns & filters\n";
        $output .= "├── InfoList/\n";
        $output .= "│   └── {Model}InfoList.php  # View page layout\n";
        $output .= "├── Grid/\n";
        $output .= "│   └── {Model}Grid.php      # Grid card layout (optional)\n";
        $output .= "├── Api/\n";
        $output .= "│   └── {Model}Api.php       # API endpoints (optional)\n";
        $output .= "└── Pages/\n";
        $output .= "    ├── List{Model}.php      # Index page\n";
        $output .= "    ├── Create{Model}.php    # Create page\n";
        $output .= "    ├── Edit{Model}.php      # Edit page\n";
        $output .= "    └── View{Model}.php      # View page\n";
        $output .= "```\n\n";

        $output .= "## Example Resource\n\n";
        $output .= "```php\n";
        $output .= "<?php\n\n";
        $output .= "namespace App\\Laravilt\\Admin\\Resources\\Product;\n\n";
        $output .= "use App\\Models\\Product;\n";
        $output .= "use Laravilt\\Panel\\Resources\\Resource;\n";
        $output .= "use Laravilt\\Schemas\\Schema;\n";
        $output .= "use Laravilt\\Tables\\Table;\n\n";
        $output .= "class ProductResource extends Resource\n";
        $output .= "{\n";
        $output .= "    protected static string \$model = Product::class;\n";
        $output .= "    protected static ?string \$navigationIcon = 'Package';\n";
        $output .= "    protected static ?string \$navigationGroup = 'Shop';\n\n";
        $output .= "    public static function form(Schema \$schema): Schema\n";
        $output .= "    {\n";
        $output .= "        return \$schema->components([\n";
        $output .= "            TextInput::make('name')->required(),\n";
        $output .= "            Textarea::make('description'),\n";
        $output .= "            NumberInput::make('price')->required(),\n";
        $output .= "        ]);\n";
        $output .= "    }\n\n";
        $output .= "    public static function table(Table \$table): Table\n";
        $output .= "    {\n";
        $output .= "        return \$table->columns([\n";
        $output .= "            TextColumn::make('name')->searchable()->sortable(),\n";
        $output .= "            TextColumn::make('price')->money(),\n";
        $output .= "            TextColumn::make('created_at')->dateTime(),\n";
        $output .= "        ]);\n";
        $output .= "    }\n";
        $output .= "}\n";
        $output .= "```\n\n";

        $output .= "## Icon Mapping (Sample)\n\n";
        $output .= "The resource generator uses smart icon mapping:\n\n";
        $count = 0;
        foreach ($this->iconMappings as $keyword => $icon) {
            $output .= "• {$keyword} → {$icon}\n";
            $count++;
            if ($count >= 15) {
                $output .= '• ... and '.count($this->iconMappings) - 15 ." more\n";
                break;
            }
        }

        return Response::text($output);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
