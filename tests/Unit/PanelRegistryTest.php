<?php

use Laravilt\Panel\Panel;
use Laravilt\Panel\PanelRegistry;

describe('PanelRegistry', function () {
    beforeEach(function () {
        $this->registry = new PanelRegistry;
    });

    it('can register a panel', function () {
        $panel = Panel::make('admin');
        $this->registry->register($panel);

        expect($this->registry->get('admin'))->toBe($panel);
    });

    it('can get all panels', function () {
        $panel1 = Panel::make('admin');
        $panel2 = Panel::make('user');

        $this->registry->register($panel1);
        $this->registry->register($panel2);

        expect($this->registry->all())->toHaveCount(2);
        expect($this->registry->all())->toContain($panel1, $panel2);
    });

    it('can get default panel', function () {
        $panel1 = Panel::make('admin');
        $panel2 = Panel::make('user')->default();

        $this->registry->register($panel1);
        $this->registry->register($panel2);

        expect($this->registry->getDefault())->toBe($panel2);
    });

    it('returns first panel when no explicit default', function () {
        $panel = Panel::make('admin');
        $this->registry->register($panel);

        // getDefault() returns first panel if no explicit default is set
        expect($this->registry->getDefault())->toBe($panel);
    });

    it('returns null for non-existent panel', function () {
        expect($this->registry->get('nonexistent'))->toBeNull();
    });

    it('can check if panel exists', function () {
        $panel = Panel::make('admin');
        $this->registry->register($panel);

        expect($this->registry->has('admin'))->toBeTrue();
        expect($this->registry->has('nonexistent'))->toBeFalse();
    });
});
