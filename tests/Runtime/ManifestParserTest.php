<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Runtime;

use LaravelDoctor\Runtime\ManifestParser;
use PHPUnit\Framework\TestCase;

final class ManifestParserTest extends TestCase
{
    public function test_parses_valid_manifest(): void
    {
        $json = json_encode([
            'routes' => [
                ['uri' => 'admin/users', 'methods' => ['POST'], 'middleware' => ['web', 'auth'], 'action' => 'A@store'],
            ],
            'config' => ['app.debug' => false, 'app.env' => 'production'],
        ]);

        $manifest = (new ManifestParser())->parse($json);

        $this->assertNotNull($manifest);
        $this->assertCount(1, $manifest->routes);
        $this->assertSame('admin/users', $manifest->routes[0]->uri);
        $this->assertSame(['web', 'auth'], $manifest->routes[0]->middleware);
        $this->assertSame('production', $manifest->config['app.env']);
    }

    public function test_returns_null_on_invalid_json(): void
    {
        $this->assertNull((new ManifestParser())->parse('{ not json'));
    }

    public function test_returns_null_when_routes_missing(): void
    {
        $this->assertNull((new ManifestParser())->parse('{"config":{}}'));
    }
}
