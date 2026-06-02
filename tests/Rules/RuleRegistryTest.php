<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules;

use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleRegistry;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase
{
    public function test_returns_all_v1_rules_with_unique_ids(): void
    {
        $rules = RuleRegistry::all();

        $this->assertContainsOnlyInstancesOf(Rule::class, $rules);
        $this->assertGreaterThanOrEqual(4, count($rules));

        $ids = array_map(fn (Rule $r) => $r->id(), $rules);
        $this->assertSame($ids, array_unique($ids), 'Los ids de regla deben ser únicos');

        $this->assertContains('no-env-outside-config', $ids);
        $this->assertContains('prefer-exists-over-count', $ids);
        $this->assertContains('no-save-in-loop-without-transaction', $ids);
        $this->assertContains('prefer-form-request-validation', $ids);
    }
}
