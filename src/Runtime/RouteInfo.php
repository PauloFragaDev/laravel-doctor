<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

final readonly class RouteInfo
{
    /**
     * @param string[] $methods
     * @param string[] $middleware
     */
    public function __construct(
        public string $uri,
        public array $methods,
        public array $middleware,
        public string $action,
    ) {
    }
}
