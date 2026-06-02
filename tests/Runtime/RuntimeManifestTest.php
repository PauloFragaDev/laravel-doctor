<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Runtime;

use LaravelDoctor\Runtime\RouteInfo;
use LaravelDoctor\Runtime\RuntimeManifest;
use PHPUnit\Framework\TestCase;

final class RuntimeManifestTest extends TestCase
{
    public function test_holds_routes_and_config(): void
    {
        $route = new RouteInfo('admin/users', ['POST'], ['web', 'auth'], 'AdminController@store');
        $manifest = new RuntimeManifest([$route], ['app.debug' => false]);

        $this->assertCount(1, $manifest->routes);
        $this->assertSame('admin/users', $manifest->routes[0]->uri);
        $this->assertSame(['POST'], $manifest->routes[0]->methods);
        $this->assertSame(['web', 'auth'], $manifest->routes[0]->middleware);
        $this->assertSame('AdminController@store', $manifest->routes[0]->action);
        $this->assertFalse($manifest->config['app.debug']);
    }
}
