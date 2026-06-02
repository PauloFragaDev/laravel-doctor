<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Runtime;

use LaravelDoctor\Runtime\ManifestParser;
use PHPUnit\Framework\TestCase;

final class ModelManifestTest extends TestCase
{
    public function test_parses_models_section(): void
    {
        $json = json_encode([
            'routes' => [],
            'config' => [],
            'models' => [
                [
                    'class' => 'App\\Models\\User',
                    'table' => 'users',
                    'casts' => ['settings'],
                    'columns' => ['id' => 'integer', 'settings' => 'json', 'meta' => 'jsonb'],
                ],
            ],
        ]);

        $manifest = (new ManifestParser())->parse($json);

        $this->assertNotNull($manifest);
        $this->assertCount(1, $manifest->models);
        $this->assertSame('App\\Models\\User', $manifest->models[0]->class);
        $this->assertSame('users', $manifest->models[0]->table);
        $this->assertSame(['settings'], $manifest->models[0]->casts);
        $this->assertSame('json', $manifest->models[0]->columns['settings']);
    }

    public function test_models_absent_yields_empty_list(): void
    {
        $manifest = (new ManifestParser())->parse('{"routes":[],"config":{}}');
        $this->assertNotNull($manifest);
        $this->assertSame([], $manifest->models);
    }
}
