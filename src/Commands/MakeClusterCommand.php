<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MakeClusterCommand extends GeneratorCommand
{
    protected $signature = 'laravilt:cluster {panel} {name}
                            {--icon=Folder : The navigation icon for the cluster}
                            {--sort=0 : The navigation sort order}
                            {--group= : The navigation group}';

    protected $description = 'Create a new panel cluster to group related pages';

    protected $type = 'Cluster';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $result = parent::handle();

        if ($result !== false) {
            // Clear caches (don't rebuild as closures can't be serialized)
            $this->components->info('Clearing caches...');
            $this->call('optimize:clear');
        }

        return $result;
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/cluster.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $panel = Str::studly($this->argument('panel'));

        return $rootNamespace."\\Laravilt\\{$panel}\\Clusters";
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $clusterName = $this->argument('name');
        $label = Str::title(Str::snake($clusterName, ' '));
        $icon = $this->option('icon') ?: 'Folder';
        $sort = $this->option('sort') ?: 0;
        $group = $this->option('group') ? "'{$this->option('group')}'" : 'null';

        $replacements = [
            '{{ icon }}' => $icon,
            '{{ label }}' => $label,
            '{{ sort }}' => $sort,
            '{{ group }}' => $group,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['panel', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'The panel to create the cluster in'],
            ['name', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'The name of the cluster'],
        ];
    }
}
