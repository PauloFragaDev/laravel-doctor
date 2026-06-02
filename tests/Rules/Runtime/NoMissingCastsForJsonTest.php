<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Runtime;

use LaravelDoctor\Rules\Runtime\NoMissingCastsForJson;
use LaravelDoctor\Runtime\ManifestEngine;
use LaravelDoctor\Runtime\ModelInfo;
use LaravelDoctor\Runtime\RuntimeManifest;
use PHPUnit\Framework\TestCase;

final class NoMissingCastsForJsonTest extends TestCase
{
    private function analyze(ModelInfo $model): array
    {
        return (new ManifestEngine([new NoMissingCastsForJson()]))
            ->inspect(new RuntimeManifest([], [], [$model]));
    }

    public function test_flags_json_column_without_cast(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\User', 'users', [], ['settings' => 'json']));
        $this->assertCount(1, $d);
        $this->assertSame('no-missing-casts-for-json', $d[0]->ruleId);
    }

    public function test_does_not_flag_json_column_with_cast(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\User', 'users', ['settings'], ['settings' => 'json']));
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_non_json_column(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\User', 'users', [], ['name' => 'varchar']));
        $this->assertCount(0, $d);
    }
}
