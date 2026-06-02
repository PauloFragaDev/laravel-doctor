<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Runtime;

use LaravelDoctor\Rules\Runtime\PreferBigintForeignKey;
use LaravelDoctor\Runtime\ManifestEngine;
use LaravelDoctor\Runtime\ModelInfo;
use LaravelDoctor\Runtime\RuntimeManifest;
use PHPUnit\Framework\TestCase;

final class PreferBigintForeignKeyTest extends TestCase
{
    private function analyze(ModelInfo $model): array
    {
        return (new ManifestEngine([new PreferBigintForeignKey()]))
            ->inspect(new RuntimeManifest([], [], [$model]));
    }

    public function test_flags_integer_fk(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\Post', 'posts', [], ['user_id' => 'integer']));
        $this->assertCount(1, $d);
        $this->assertSame('prefer-bigint-foreign-key', $d[0]->ruleId);
    }

    public function test_does_not_flag_bigint_fk(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\Post', 'posts', [], ['user_id' => 'bigint']));
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_non_fk_integer(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\Post', 'posts', [], ['views' => 'integer']));
        $this->assertCount(0, $d);
    }
}
