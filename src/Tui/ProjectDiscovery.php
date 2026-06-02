<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

final class ProjectDiscovery
{
    /**
     * Descubre proyectos Laravel: el propio $baseDir si es una app Laravel, y cada subcarpeta
     * directa que contenga un fichero `artisan`. Ordenados por nombre.
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

        // El propio directorio base, si ya es una app Laravel: permite ejecutar el menú dentro
        // de un proyecto y auditarlo directamente, sin pasar --base.
        if (is_file($baseDir . '/artisan')) {
            $projects[] = new ProjectInfo(basename($baseDir) ?: $baseDir, $baseDir);
        }

        foreach (glob($baseDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_file($dir . '/artisan')) {
                $projects[] = new ProjectInfo(basename($dir), $dir);
            }
        }

        usort($projects, fn (ProjectInfo $a, ProjectInfo $b) => strcmp($a->name, $b->name));

        return $projects;
    }
}
