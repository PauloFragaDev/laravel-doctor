<?php

declare(strict_types=1);

namespace LaravelDoctor\Laravel;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

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

    protected $description = 'Emite el manifiesto de runtime (rutas, config) para laravel-doctor.';

    public function handle(): int
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

        $manifest = [
            'routes' => $routes,
            'config' => [
                'app.env' => config('app.env'),
                'app.debug' => config('app.debug'),
            ],
        ];

        $this->output->writeln(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
