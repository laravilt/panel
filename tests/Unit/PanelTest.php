<?php

use Laravilt\Panel\Panel;

describe('Panel', function () {
    it('can be instantiated with an id', function () {
        $panel = Panel::make('admin');

        expect($panel)->toBeInstanceOf(Panel::class);
        expect($panel->getId())->toBe('admin');
    });

    it('can set and get path', function () {
        $panel = Panel::make('admin')->path('dashboard');

        expect($panel->getPath())->toBe('dashboard');
    });

    it('defaults path to id', function () {
        $panel = Panel::make('admin');

        expect($panel->getPath())->toBe('admin');
    });

    it('can be set as default', function () {
        $panel = Panel::make('admin')->default();

        expect($panel->isDefault())->toBeTrue();
    });

    it('can set brand name', function () {
        $panel = Panel::make('admin')->brandName('My App');

        expect($panel->getBrandName())->toBe('My App');
    });

    it('can set brand logo', function () {
        $panel = Panel::make('admin')->brandLogo('/images/logo.svg');

        expect($panel->getBrandLogo())->toBe('/images/logo.svg');
    });

    it('can set middleware', function () {
        $panel = Panel::make('admin')->middleware(['web', 'auth']);

        expect($panel->getMiddleware())->toBe(['web', 'auth']);
    });

    it('can set middleware (replaces previous)', function () {
        $panel = Panel::make('admin')
            ->middleware(['web'])
            ->middleware(['auth']);

        expect($panel->getMiddleware())->toBe(['auth']);
    });

    it('can set auth middleware', function () {
        $panel = Panel::make('admin')->authMiddleware(['auth:sanctum']);

        expect($panel->getAuthMiddleware())->toBe(['auth:sanctum']);
    });

    it('can generate url', function () {
        $panel = Panel::make('admin')->path('admin');

        expect($panel->url('/'))->toBe('/admin/');
        expect($panel->url('/users'))->toBe('/admin/users');
    });
});

describe('Panel Pages', function () {
    it('can register pages', function () {
        $panel = Panel::make('admin')->pages([
            \Laravilt\Panel\Pages\Dashboard::class,
        ]);

        expect($panel->getPages())->toContain(\Laravilt\Panel\Pages\Dashboard::class);
    });

    it('starts with empty pages', function () {
        $panel = Panel::make('admin');

        expect($panel->getPages())->toBeArray();
    });
});

describe('Panel Resources', function () {
    it('starts with empty resources', function () {
        $panel = Panel::make('admin');

        expect($panel->getResources())->toBeArray();
    });
});

describe('Panel Widgets', function () {
    it('starts with empty widgets', function () {
        $panel = Panel::make('admin');

        expect($panel->getWidgets())->toBeArray();
    });
});
