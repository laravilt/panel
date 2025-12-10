<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakePageCommand extends GeneratorCommand
{
    protected $signature = 'laravilt:page {panel} {name}';

    protected $description = 'Create a new panel page';

    protected $type = 'Page';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $result = parent::handle();

        if ($result !== false) {
            $this->createVueViewFile();
        }

        return $result;
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/page.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $panel = Str::studly($this->argument('panel'));

        return $rootNamespace."\\Laravilt\\{$panel}\\Pages";
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $panel = Str::studly($this->argument('panel'));
        $pageName = Str::studly($this->argument('name'));

        $replacements = [
            '{{ panel }}' => $panel,
            '{{ name }}' => $pageName,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );
    }

    /**
     * Create the Vue view file for the page.
     */
    protected function createVueViewFile(): void
    {
        $panel = Str::studly($this->argument('panel'));
        $name = Str::studly($this->argument('name'));

        $viewPath = resource_path("js/pages/{$panel}");
        $viewFile = "{$viewPath}/{$name}.vue";

        if (File::exists($viewFile)) {
            $this->components->error("Vue view already exists: {$viewFile}");

            return;
        }

        File::ensureDirectoryExists($viewPath);

        $stub = File::get(__DIR__.'/../../stubs/page-view.stub');
        $content = str_replace('{{ name }}', $name, $stub);

        File::put($viewFile, $content);

        $this->components->info("Vue view created: {$viewFile}");
    }
}
