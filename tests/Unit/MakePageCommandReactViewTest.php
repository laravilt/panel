<?php

use Laravilt\Panel\Commands\MakePageCommand;

function reactPageView(string $name, string $type): string
{
    $command = new class extends MakePageCommand
    {
        public function reactView(string $name, string $type): string
        {
            $this->pageType = $type;

            return $this->generateReactView($name);
        }
    };

    return $command->reactView($name, $type);
}

it('generates a React page component for each page type', function (string $type, string $marker) {
    $view = reactPageView('SalesReport', $type);

    expect($view)
        ->toContain("import { Head } from '@inertiajs/react';")
        ->toContain("import Heading from '@/components/heading';")
        ->toContain('export default function SalesReport()')
        ->toContain('<Head title="Sales Report" />')
        ->toContain('<Heading title="Sales Report" />')
        ->toContain($marker)
        ->not->toContain('.vue')
        ->not->toContain('<template>');
})->with([
    'basic' => ['basic', 'This is the SalesReport page.'],
    'dashboard' => ['dashboard', '{/* Add widget components here */}'],
    'form' => ['form', "import { Button } from '@/components/ui/button';"],
    'table' => ['table', '<CardTitle>All Records</CardTitle>'],
]);
