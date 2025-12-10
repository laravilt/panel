<?php

use Laravilt\Panel\Navigation\NavigationItem;

describe('NavigationItem', function () {
    it('can be created with a label', function () {
        $item = NavigationItem::make('Dashboard');

        expect($item->getLabel())->toBe('Dashboard');
    });

    it('can set url', function () {
        $item = NavigationItem::make('Dashboard')->url('/admin');

        expect($item->getUrl())->toBe('/admin');
    });

    it('can set icon', function () {
        $item = NavigationItem::make('Dashboard')->icon('LayoutDashboard');

        expect($item->getIcon())->toBe('LayoutDashboard');
    });

    it('can set sort order', function () {
        $item = NavigationItem::make('Dashboard')->sort(10);

        expect($item->getSort())->toBe(10);
    });

    it('can set badge', function () {
        $item = NavigationItem::make('Notifications')->badge(5);

        expect($item->getBadge())->toBe('5');
    });

    it('can set badge color', function () {
        $item = NavigationItem::make('Notifications')->badge(5, 'danger');

        expect($item->getBadgeColor())->toBe('danger');
    });

    it('has default badge color', function () {
        $item = NavigationItem::make('Notifications')->badge(5);

        expect($item->getBadgeColor())->toBe('primary');
    });

    it('can convert to array', function () {
        $item = NavigationItem::make('Dashboard')
            ->url('/admin')
            ->icon('LayoutDashboard')
            ->sort(1);

        $array = $item->toArray();

        expect($array)->toHaveKeys(['title', 'url', 'icon', 'sort']);
        expect($array['title'])->toBe('Dashboard');
        expect($array['url'])->toBe('/admin');
        expect($array['icon'])->toBe('LayoutDashboard');
    });

    it('can set active condition', function () {
        $item = NavigationItem::make('Dashboard')
            ->url('/admin')
            ->active(true);

        expect($item->isActive())->toBeTrue();
    });

    it('defaults active to false', function () {
        $item = NavigationItem::make('Dashboard')->url('/admin');

        expect($item->isActive())->toBeFalse();
    });

    it('can set translation key', function () {
        $item = NavigationItem::make('Dashboard')
            ->translationKey('navigation.dashboard');

        expect($item->getTranslationKey())->toBe('navigation.dashboard');
    });

    it('can set method', function () {
        $item = NavigationItem::make('Logout')
            ->method('POST');

        expect($item->getMethod())->toBe('POST');
    });
});
