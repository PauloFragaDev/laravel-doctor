<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Runtime;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Runtime\ManifestRule;
use LaravelDoctor\Runtime\ManifestRuleContext;
use LaravelDoctor\Runtime\RouteInfo;
use LaravelDoctor\Runtime\RuntimeManifest;

final class NoRouteWithoutAuth implements ManifestRule
{
    private const STATE_CHANGING = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const AUTH_MIDDLEWARE_PREFIXES = ['auth', 'auth.basic', 'authenticate'];

    public function id(): string { return 'no-route-without-auth'; }

    public function title(): string { return 'Ruta que cambia estado sin middleware de auth'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Protege la ruta con un middleware de autenticación (auth) o confirma explícitamente que debe ser pública.';
    }

    public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void
    {
        foreach ($manifest->routes as $route) {
            if (!$this->isStateChanging($route) || $this->hasAuthMiddleware($route)) {
                continue;
            }

            $method = $this->stateChangingMethod($route);
            $context->report('routes', 0, sprintf('Ruta %s %s cambia estado y no tiene middleware de auth.', $method, $route->uri));
        }
    }

    private function isStateChanging(RouteInfo $route): bool
    {
        return $this->stateChangingMethod($route) !== null;
    }

    private function stateChangingMethod(RouteInfo $route): ?string
    {
        foreach ($route->methods as $method) {
            if (in_array(strtoupper($method), self::STATE_CHANGING, true)) {
                return strtoupper($method);
            }
        }

        return null;
    }

    private function hasAuthMiddleware(RouteInfo $route): bool
    {
        foreach ($route->middleware as $middleware) {
            foreach (self::AUTH_MIDDLEWARE_PREFIXES as $prefix) {
                if ($middleware === $prefix || str_starts_with($middleware, $prefix . ':')) {
                    return true;
                }
            }
        }

        return false;
    }
}
