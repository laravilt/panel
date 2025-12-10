<?php

use Laravilt\Panel\Pages\Page;

class TestPage extends Page
{
    protected static ?string $title = 'Test Page';

    protected static ?string $navigationIcon = 'TestIcon';

    protected static ?string $slug = 'test-page';

    protected static string $view = 'test';
}

class HiddenPage extends Page
{
    protected static ?string $slug = 'hidden';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'hidden';
}

describe('Page', function () {
    it('can get title', function () {
        expect(TestPage::getTitle())->toBe('Test Page');
    });

    it('can get navigation icon', function () {
        expect(TestPage::getNavigationIcon())->toBe('TestIcon');
    });

    it('can get slug', function () {
        expect(TestPage::getSlug())->toBe('test-page');
    });

    it('can check navigation registration', function () {
        expect(TestPage::shouldRegisterNavigation())->toBeTrue();
        expect(HiddenPage::shouldRegisterNavigation())->toBeFalse();
    });
});
