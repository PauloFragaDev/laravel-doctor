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
        $this->assertCount(10, $rules);

        $ids = array_map(fn (Rule $r) => $r->id(), $rules);
        $this->assertSame($ids, array_unique($ids), 'Los ids de regla deben ser únicos');

        foreach ([
            'no-env-outside-config',
            'prefer-exists-over-count',
            'no-save-in-loop-without-transaction',
            'prefer-form-request-validation',
            'no-mass-assignment-guarded-empty',
            'no-raw-sql-interpolation',
            'no-query-in-loop',
            'no-all-then-filter',
            'no-fat-controller-method',
            'no-business-logic-in-route-closure',
        ] as $expected) {
            $this->assertContains($expected, $ids);
        }
    }
}
