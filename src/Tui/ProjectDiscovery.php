<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

final class ProjectDiscovery
{
    /**
     * Descubre proyectos Laravel directamente bajo $baseDir: una subcarpeta es un proyecto
     * Laravel si contiene un fichero `artisan`. Ordenados por nombre.
     *
     * @return ProjectInfo[]
     */
    public function discover(string $baseDir): array
    {
        $baseDir = rtrim($baseDir, '/');
        if (!is_dir($baseDir)) {
            return [];
        }

        $projects = [];
        foreach (glob($baseDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_file($dir . '/artisan')) {
                $projects[] = new ProjectInfo(basename($dir), $dir);
            }
        }

        usort($projects, fn (ProjectInfo $a, ProjectInfo $b) => strcmp($a->name, $b->name));

        return $projects;
    }
}
