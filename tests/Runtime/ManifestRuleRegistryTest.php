<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Runtime;

use LaravelDoctor\Runtime\ManifestRule;
use LaravelDoctor\Runtime\ManifestRuleRegistry;
use PHPUnit\Framework\TestCase;

final class ManifestRuleRegistryTest extends TestCase
{
    public function test_returns_manifest_rules_with_unique_ids(): void
    {
        $rules = ManifestRuleRegistry::all();

        $this->assertContainsOnlyInstancesOf(ManifestRule::class, $rules);
        $ids = array_map(fn (ManifestRule $r) => $r->id(), $rules);
        $this->assertSame($ids, array_unique($ids));
        $this->assertContains('no-route-without-auth', $ids);
    }
}
