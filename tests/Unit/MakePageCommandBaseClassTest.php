<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravilt\Panel\Commands\MakePageCommand;
use Laravilt\Panel\Pages\Dashboard;
use Laravilt\Panel\Pages\Page;

function generatePageClassFile(string $panel, string $name, string $type, array $features = []): string
{
    $command = new class extends MakePageCommand
    {
        public function writeClass(string $panel, string $name, string $type, array $features): string
        {
            $this->pageType = $type;
            $this->selectedFeatures = $features;

            $path = app_path("Laravilt/{$panel}/Pages/{$name}.php");
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $this->generatePageClass($panel, $name));

            return $path;
        }
    };

    return $command->writeClass($panel, $name, $type, $features);
}

beforeEach(function () {
    $this->appPath = sys_get_temp_dir().'/laravilt-page-'.Str::random(8);
    File::ensureDirectoryExists($this->appPath);
    $this->app->useAppPath($this->appPath);
});

afterEach(function () {
    File::deleteDirectory($this->appPath);
});

it('maps every page type to an existing base class without unimplemented abstract methods', function (string $type, string $expected) {
    $baseClass = MakePageCommand::baseClassFor($type);

    expect($baseClass)->toBe($expected)
        ->and(class_exists($baseClass))->toBeTrue();

    $abstractMethods = array_filter(
        (new ReflectionClass($baseClass))->getMethods(),
        fn (ReflectionMethod $method) => $method->isAbstract(),
    );

    expect($abstractMethods)->toBeEmpty();
})->with([
    'basic' => ['basic', Page::class],
    'form' => ['form', Page::class],
    'table' => ['table', Page::class],
    'dashboard' => ['dashboard', Dashboard::class],
]);

it('generates an instantiable page class for each page type', function (string $type, string $expectedParent) {
    $name = 'Generated'.Str::studly($type).'Page'.Str::random(6);
    $path = generatePageClassFile('Admin', $name, $type, ['header-actions', 'footer-actions', 'breadcrumbs', 'widgets', 'polling']);

    require_once $path;

    $class = "App\\Laravilt\\Admin\\Pages\\{$name}";
    $reflection = new ReflectionClass($class);
    $parent = $reflection->getParentClass();

    expect($parent)->not->toBeFalse()
        ->and($parent->getName())->toBe($expectedParent)
        ->and(class_exists($parent->getName()))->toBeTrue()
        ->and($reflection->isInstantiable())->toBeTrue()
        ->and(File::get($path))->toContain("protected static string \$view = 'Admin/{$name}';");

    $page = new $class;

    expect($page->getHeaderActions())->toHaveCount(1)
        ->and($page->getWidgets())->toBe([]);
})->with([
    'basic' => ['basic', Page::class],
    'form' => ['form', Page::class],
    'table' => ['table', Page::class],
    'dashboard' => ['dashboard', Dashboard::class],
]);
