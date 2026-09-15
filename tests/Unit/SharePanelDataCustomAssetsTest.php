<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Laravilt\Panel\Http\Middleware\SharePanelData;
use Laravilt\Panel\Panel;
use Laravilt\Panel\PanelRegistry;

it('shares resolved custom CSS and JS URLs with the frontend', function () {
    url()->forceRootUrl('http://example.test');

    $panel = Panel::make('admin')
        ->customCss(['css/admin.css', '/build/theme.css', 'https://cdn.example.com/a.css', 'css/admin.css'])
        ->customJs(fn () => ['js/admin.js', '//cdn.example.com/b.js']);

    $registry = app(PanelRegistry::class);
    $registry->register($panel);
    $registry->setCurrent('admin');

    (new SharePanelData)->handle(Request::create('/admin'), fn () => new Response);

    $shared = Inertia::getShared('panel');
    $shared = is_callable($shared) ? $shared() : $shared;

    expect(asset('css/admin.css'))->toEndWith('example.test/css/admin.css');

    expect($shared['customCss'])->toBe([
        asset('css/admin.css'),
        '/build/theme.css',
        'https://cdn.example.com/a.css',
    ])->and($shared['customJs'])->toBe([
        asset('js/admin.js'),
        '//cdn.example.com/b.js',
    ]);
});
