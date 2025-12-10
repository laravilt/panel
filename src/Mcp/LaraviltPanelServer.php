<?php

namespace Laravilt\Panel\Mcp;

use Laravel\Mcp\Server;
use Laravilt\Panel\Mcp\Tools\GetResourceInfoTool;
use Laravilt\Panel\Mcp\Tools\ListPanelFeaturesTool;
use Laravilt\Panel\Mcp\Tools\SearchDocsTool;

class LaraviltPanelServer extends Server
{
    protected string $name = 'Laravilt Panel';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        This server provides admin panel capabilities for Laravilt projects.

        You can:
        - Search panel documentation
        - List available panel features and configuration options
        - Get information about resources, pages, and navigation
        - Generate panels, resources, and pages using artisan commands

        Available generator commands:
        - php artisan laravilt:panel {id} --path={path}
        - php artisan laravilt:page {panel} {name}
        - php artisan laravilt:resource {panel} --table={table}

        Panel supports resources, pages, widgets, navigation groups, multi-tenancy, and authentication integration.
    MARKDOWN;

    protected array $tools = [
        SearchDocsTool::class,
        ListPanelFeaturesTool::class,
        GetResourceInfoTool::class,
    ];
}
