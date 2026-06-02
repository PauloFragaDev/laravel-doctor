<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleRegistry;
use PHPUnit\Framework\TestCase;

final class BladeRuleRegistryTest extends TestCase
{
    public function test_returns_blade_rules_with_unique_ids(): void
    {
        $rules = BladeRuleRegistry::all();

        $this->assertContainsOnlyInstancesOf(BladeRule::class, $rules);
        $this->assertCount(2, $rules);

        $ids = array_map(fn (BladeRule $r) => $r->id(), $rules);
        $this->assertSame($ids, array_unique($ids));
        $this->assertContains('no-unescaped-blade-output', $ids);
        $this->assertContains('no-logic-in-blade', $ids);
    }
}
