<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Runtime;

use LaravelDoctor\Rules\Runtime\NoUnindexedForeignKey;
use LaravelDoctor\Runtime\ManifestEngine;
use LaravelDoctor\Runtime\ModelInfo;
use LaravelDoctor\Runtime\RuntimeManifest;
use PHPUnit\Framework\TestCase;

final class NoUnindexedForeignKeyTest extends TestCase
{
    private function analyze(ModelInfo $model): array
    {
        return (new ManifestEngine([new NoUnindexedForeignKey()]))
            ->inspect(new RuntimeManifest([], [], [$model]));
    }

    public function test_flags_unindexed_fk_column(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\Post', 'posts', [], ['id' => 'bigint', 'user_id' => 'bigint'], ['id']));
        $this->assertCount(1, $d);
        $this->assertSame('no-unindexed-foreign-key', $d[0]->ruleId);
    }

    public function test_does_not_flag_indexed_fk(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\Post', 'posts', [], ['user_id' => 'bigint'], ['user_id']));
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_non_fk_columns(): void
    {
        $d = $this->analyze(new ModelInfo('App\\Models\\Post', 'posts', [], ['title' => 'varchar', 'id' => 'bigint'], []));
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_non_integer_id_column(): void
    {
        // un *_id no entero (p. ej. uuid string) no se asume FK indexable de la misma forma
        $d = $this->analyze(new ModelInfo('App\\Models\\Post', 'posts', [], ['external_id' => 'varchar'], []));
        $this->assertCount(0, $d);
    }
}
