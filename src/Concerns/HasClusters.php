<?php

namespace Laravilt\Panel\Concerns;

use Illuminate\Support\Facades\File;
use Laravilt\Panel\Cluster;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

trait HasClusters
{
    protected array $clusters = [];

    protected array $clusterDirectories = [];

    /**
     * Register clusters.
     */
    public function clusters(array $clusters): static
    {
        $this->clusters = array_merge($this->clusters, $clusters);

        return $this;
    }

    /**
     * Get registered clusters.
     */
    public function getClusters(): array
    {
        return $this->clusters;
    }

    /**
     * Discover clusters in a directory.
     */
    public function discoverClusters(string $in, string $for): static
    {
        $this->clusterDirectories[] = [
            'directory' => $in,
            'namespace' => $for,
        ];

        return $this;
    }

    /**
     * Boot clusters - discover and register.
     */
    protected function bootClusters(): void
    {
        foreach ($this->clusterDirectories as $config) {
            if (! is_dir($config['directory'])) {
                continue;
            }

            $clusters = collect(File::allFiles($config['directory']))
                ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
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

                    // Check if it's a Cluster class
                    if (! $reflection->isSubclassOf(Cluster::class)) {
                        return;
                    }

                    return $class;
                })
                ->filter()
                ->all();

            $this->clusters(array_merge($this->clusters, $clusters));
        }
    }
}
