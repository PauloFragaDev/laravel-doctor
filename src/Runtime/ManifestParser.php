<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

final class ManifestParser
{
    /**
     * Parsea el JSON emitido por `php artisan laravel-doctor:manifest`.
     * Devuelve null si el JSON no es válido o no tiene la forma esperada.
     */
    public function parse(string $json): ?RuntimeManifest
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['routes']) || !is_array($data['routes'])) {
            return null;
        }

        $routes = [];
        foreach ($data['routes'] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $routes[] = new RouteInfo(
                uri: (string) ($route['uri'] ?? ''),
                methods: array_map('strval', (array) ($route['methods'] ?? [])),
                middleware: array_map('strval', (array) ($route['middleware'] ?? [])),
                action: (string) ($route['action'] ?? ''),
            );
        }

        $config = isset($data['config']) && is_array($data['config']) ? $data['config'] : [];

        $models = [];
        foreach ((array) ($data['models'] ?? []) as $model) {
            if (!is_array($model)) {
                continue;
            }
            $columns = [];
            foreach ((array) ($model['columns'] ?? []) as $name => $type) {
                $columns[(string) $name] = (string) $type;
            }
            $models[] = new ModelInfo(
                class: (string) ($model['class'] ?? ''),
                table: (string) ($model['table'] ?? ''),
                casts: array_map('strval', (array) ($model['casts'] ?? [])),
                columns: $columns,
            );
        }

        return new RuntimeManifest($routes, $config, $models);
    }
}
