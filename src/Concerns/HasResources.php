<?php

namespace Laravilt\Panel\Concerns;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

trait HasResources
{
    protected array $resources = [];

    protected array $resourceDirectories = [];

    /**
     * Register resources.
     */
    public function resources(array $resources): static
    {
        $this->resources = array_merge($this->resources, $resources);

        return $this;
    }

    /**
     * Get registered resources.
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Discover resources in a directory.
     */
    public function discoverResources(string $in, string $for): static
    {
        $this->resourceDirectories[] = [
            'directory' => $in,
            'namespace' => $for,
        ];

        return $this;
    }

    /**
     * Boot resources - discover and register.
     */
    protected function bootResources(): void
    {
        foreach ($this->resourceDirectories as $config) {
            if (! is_dir($config['directory'])) {
                continue;
            }

            $resources = collect(File::allFiles($config['directory']))
                ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
                ->filter(function (SplFileInfo $file) {
                    // Exclude files in configuration subdirectories (Pages, Form, Table, Grid, InfoList, Api, Flutter, RelationManagers)
                    $excludedDirs = ['Pages', 'Form', 'Table', 'Grid', 'InfoList', 'Api', 'Flutter', 'RelationManagers'];
                    foreach ($excludedDirs as $dir) {
                        if (str_contains($file->getRelativePath(), $dir)) {
                            return false;
                        }
                    }
                    return true;
                })
                ->map(function (SplFileInfo $file) use ($config) {
                    $class = $config['namespace'].'\\'.
                        str_replace(
                            ['/', '.php'],
                            ['\\', ''],
                            $file->getRelativePathname()
                        );

                    if (! class_exists($class)) {
                        return;
                    }

                    $reflection = new ReflectionClass($class);

                    if ($reflection->isAbstract() || $reflection->isInterface()) {
                        return;
                    }

                    return $class;
                })
                ->filter()
                ->all();

            $this->resources = array_unique(array_merge($this->resources, $resources));
        }
    }
}
