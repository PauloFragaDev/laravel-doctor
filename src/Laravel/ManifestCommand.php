<?php

declare(strict_types=1);

namespace LaravelDoctor\Laravel;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;

/**
 * Comando que corre DENTRO de la app Laravel del usuario (vía package discovery) y emite
 * el manifiesto de runtime como JSON. laravel-doctor lo invoca con --boot.
 *
 * Integración-only: depende de Illuminate, que solo existe cuando el paquete está instalado
 * en una app Laravel real. No se cubre con unit tests.
 */
final class ManifestCommand extends Command
{
    protected $signature = 'laravel-doctor:manifest {--json : Emite el manifiesto como JSON}';

    protected $description = 'Emite el manifiesto de runtime (rutas, config, modelos) para laravel-doctor.';

    public function handle(): int
    {
        $manifest = [
            'routes' => $this->routes(),
            'config' => [
                'app.env' => config('app.env'),
                'app.debug' => config('app.debug'),
            ],
            'models' => $this->models(),
        ];

        $this->output->writeln(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function routes(): array
    {
        $routes = [];
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            $routes[] = [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'middleware' => array_values($route->gatherMiddleware()),
                'action' => $route->getActionName(),
            ];
        }

        return $routes;
    }

    /**
     * Descubre los modelos de app/Models y reúne tabla, casts y columnas de la DB.
     * Best-effort: cualquier modelo que falle (sin DB, no instanciable) se omite.
     *
     * @return array<int,array<string,mixed>>
     */
    private function models(): array
    {
        $dir = app_path('Models');
        if (!is_dir($dir)) {
            return [];
        }

        $models = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');
            if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
                continue;
            }

            try {
                /** @var Model $instance */
                $instance = new $class();
                $table = $instance->getTable();
                $columns = [];
                foreach (Schema::getColumns($table) as $column) {
                    $columns[(string) $column['name']] = (string) $column['type_name'];
                }
                $models[] = [
                    'class' => $class,
                    'table' => $table,
                    'casts' => array_keys($instance->getCasts()),
                    'columns' => $columns,
                ];
            } catch (\Throwable) {
                // Sin DB o modelo no instanciable: se omite (las reglas de modelo no dispararán).
            }
        }

        return $models;
    }
}
