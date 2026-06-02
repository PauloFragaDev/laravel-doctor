<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Runtime;

use LaravelDoctor\Runtime\ManifestExtractor;
use PHPUnit\Framework\TestCase;

final class ManifestExtractorTest extends TestCase
{
    public function test_returns_manifest_on_success(): void
    {
        $json = '{"routes":[{"uri":"x","methods":["GET"],"middleware":["web"],"action":"A@i"}],"config":{}}';
        $extractor = new ManifestExtractor(fn (string $dir) => [0, $json]);

        $manifest = $extractor->extract('/fake/project');

        $this->assertNotNull($manifest);
        $this->assertSame('x', $manifest->routes[0]->uri);
    }

    public function test_returns_null_when_command_fails(): void
    {
        $extractor = new ManifestExtractor(fn (string $dir) => [1, '']);
        $this->assertNull($extractor->extract('/fake/project'));
    }

    public function test_returns_null_when_output_is_not_valid_manifest(): void
    {
        $extractor = new ManifestExtractor(fn (string $dir) => [0, 'boom not json']);
        $this->assertNull($extractor->extract('/fake/project'));
    }
}
