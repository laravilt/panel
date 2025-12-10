<?php

namespace Laravilt\Panel\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListPanelFeaturesTool extends Tool
{
    protected string $description = 'List all available panel features with their configuration options';

    protected array $features = [
        'Basic Configuration' => [
            'description' => 'Core panel setup and URL configuration',
            'methods' => [
                'Panel::make(string $id)' => 'Create a new panel with ID',
                '->path(string $path)' => 'Set URL path (e.g., /admin)',
                '->domain(string $domain)' => 'Set custom domain',
                '->authGuard(string $guard)' => 'Set authentication guard',
                '->middleware(array $middleware)' => 'Add middleware stack',
            ],
            'example' => "Panel::make('admin')\n    ->path('/admin')\n    ->authGuard('web')\n    ->middleware(['auth', 'verified']);",
        ],
        'Authentication' => [
            'description' => 'Login, registration, and authentication features',
            'methods' => [
                '->login(bool|string $page = true)' => 'Enable login page',
                '->registration(bool|string $page)' => 'Enable registration',
                '->emailVerification()' => 'Require email verification',
                '->otp()' => 'Enable OTP verification',
                '->twoFactor(array $config)' => 'Enable 2FA',
                '->passwordReset()' => 'Enable password reset',
                '->socialLogin(array $providers)' => 'Enable social auth',
                '->passkeys()' => 'Enable passkey authentication',
                '->magicLinks()' => 'Enable magic link login',
            ],
            'example' => "->login()\n->registration()\n->passwordReset()\n->twoFactor()\n->socialLogin(['github', 'google'])",
        ],
        'Navigation' => [
            'description' => 'Sidebar navigation configuration',
            'methods' => [
                '->navigation(array $items)' => 'Set navigation items',
                '->navigationGroups(array $groups)' => 'Define navigation groups',
            ],
            'example' => "->navigationGroups([\n    'Content',\n    'Settings',\n])",
        ],
        'Pages & Resources' => [
            'description' => 'Register pages and CRUD resources',
            'methods' => [
                '->pages(array $pages)' => 'Register custom pages',
                '->resources(array $resources)' => 'Register CRUD resources',
                '->widgets(array $widgets)' => 'Register dashboard widgets',
            ],
            'example' => "->pages([\n    Dashboard::class,\n    Settings::class,\n])\n->resources([\n    UserResource::class,\n    ProductResource::class,\n])",
        ],
        'Branding' => [
            'description' => 'Customize panel appearance',
            'methods' => [
                '->brandName(string $name)' => 'Set brand name',
                '->brandLogo(string $url)' => 'Set logo URL',
                '->brandUrl(string $url)' => 'Set brand link',
                '->favicon(string $url)' => 'Set favicon',
            ],
            'example' => "->brandName('My Admin')\n->brandLogo('/images/logo.svg')\n->favicon('/favicon.ico')",
        ],
        'Multi-tenancy' => [
            'description' => 'Tenant-aware panel configuration',
            'methods' => [
                '->tenant(Model $model)' => 'Set tenant model',
                '->tenantMiddleware(array $middleware)' => 'Add tenant middleware',
                '->tenantRegistration()' => 'Enable tenant registration',
            ],
            'example' => "->tenant(Team::class)\n->tenantRegistration()\n->tenantMiddleware(['tenant.verified'])",
        ],
    ];

    public function handle(Request $request): Response
    {
        $output = "📊 Laravilt Panel - Available Features\n\n";
        $output .= str_repeat('=', 70)."\n\n";

        foreach ($this->features as $name => $details) {
            $output .= "## {$name}\n\n";
            $output .= "{$details['description']}\n\n";
            $output .= "Methods:\n";
            foreach ($details['methods'] as $method => $desc) {
                $output .= "  • `{$method}` - {$desc}\n";
            }
            $output .= "\nExample:\n```php\n{$details['example']}\n```\n\n";
            $output .= str_repeat('-', 70)."\n\n";
        }

        $output .= "\n## Generator Commands\n\n";
        $output .= "Create a new panel:\n";
        $output .= "```bash\nphp artisan laravilt:panel admin\nphp artisan laravilt:panel tenant --path=/tenant/{tenant}\n```\n\n";
        $output .= "Create a page:\n";
        $output .= "```bash\nphp artisan laravilt:page admin Settings\nphp artisan laravilt:page admin Users/Profile\n```\n\n";
        $output .= "Create a resource:\n";
        $output .= "```bash\nphp artisan laravilt:resource admin --table=products\nphp artisan laravilt:resource admin --table=orders --model=Order\n```\n";

        return Response::text($output);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
