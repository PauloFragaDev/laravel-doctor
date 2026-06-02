<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Runtime;

use LaravelDoctor\Rules\Runtime\NoDebugInProduction;
use LaravelDoctor\Runtime\ManifestEngine;
use LaravelDoctor\Runtime\RuntimeManifest;
use PHPUnit\Framework\TestCase;

final class NoDebugInProductionTest extends TestCase
{
    private function analyze(array $config): array
    {
        return (new ManifestEngine([new NoDebugInProduction()]))
            ->inspect(new RuntimeManifest([], $config));
    }

    public function test_flags_production_with_debug_on(): void
    {
        $d = $this->analyze(['app.env' => 'production', 'app.debug' => true]);
        $this->assertCount(1, $d);
        $this->assertSame('no-debug-in-production', $d[0]->ruleId);
    }

    public function test_does_not_flag_production_with_debug_off(): void
    {
        $d = $this->analyze(['app.env' => 'production', 'app.debug' => false]);
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_local_with_debug_on(): void
    {
        $d = $this->analyze(['app.env' => 'local', 'app.debug' => true]);
        $this->assertCount(0, $d);
    }
}
