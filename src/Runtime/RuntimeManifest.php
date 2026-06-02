<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

/**
 * Datos resueltos en runtime (rutas, config) extraídos de la app vía el comando artisan.
 * Reemplaza a la interfaz-marcador del v1: ahora es el value object real que consumen las
 * reglas de manifiesto y, en el futuro, reglas AST que quieran datos de runtime.
 */
final readonly class RuntimeManifest
{
    /**
     * @param RouteInfo[] $routes
     * @param array<string,mixed> $config
     */
    public function __construct(
        public array $routes,
        public array $config,
    ) {
    }
}
