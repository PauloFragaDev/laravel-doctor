<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Runtime;

use LaravelDoctor\Rules\Runtime\NoRouteWithoutAuth;
use LaravelDoctor\Runtime\ManifestEngine;
use LaravelDoctor\Runtime\RouteInfo;
use LaravelDoctor\Runtime\RuntimeManifest;
use PHPUnit\Framework\TestCase;

final class NoRouteWithoutAuthTest extends TestCase
{
    private function analyze(RouteInfo ...$routes): array
    {
        return (new ManifestEngine([new NoRouteWithoutAuth()]))
            ->inspect(new RuntimeManifest($routes, []));
    }

    public function test_flags_post_route_without_auth(): void
    {
        $d = $this->analyze(new RouteInfo('admin/users', ['POST'], ['web'], 'A@store'));
        $this->assertCount(1, $d);
        $this->assertSame('no-route-without-auth', $d[0]->ruleId);
    }

    public function test_does_not_flag_get_route_without_auth(): void
    {
        $d = $this->analyze(new RouteInfo('home', ['GET'], ['web'], 'A@index'));
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_post_route_with_auth(): void
    {
        $d = $this->analyze(new RouteInfo('admin/users', ['POST'], ['web', 'auth'], 'A@store'));
        $this->assertCount(0, $d);
    }

    public function test_recognizes_auth_with_guard(): void
    {
        $d = $this->analyze(new RouteInfo('admin/users', ['PUT'], ['auth:sanctum'], 'A@update'));
        $this->assertCount(0, $d);
    }
}
