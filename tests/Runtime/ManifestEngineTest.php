<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Runtime;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Runtime\ManifestRule;
use LaravelDoctor\Runtime\ManifestRuleContext;
use LaravelDoctor\Runtime\RouteInfo;
use LaravelDoctor\Runtime\RuntimeManifest;
use LaravelDoctor\Runtime\ManifestEngine;
use PHPUnit\Framework\TestCase;

final class ManifestEngineTest extends TestCase
{
    private function manifest(): RuntimeManifest
    {
        return new RuntimeManifest([new RouteInfo('x', ['GET'], ['web'], 'A@i')], []);
    }

    public function test_runs_rules_and_collects_diagnostics(): void
    {
        $rule = new class implements ManifestRule {
            public function id(): string { return 'counts-routes'; }
            public function title(): string { return 't'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'r'; }
            public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void
            {
                foreach ($manifest->routes as $route) {
                    $context->report('routes', 0, 'route ' . $route->uri);
                }
            }
        };

        $d = (new ManifestEngine([$rule]))->inspect($this->manifest());

        $this->assertCount(1, $d);
        $this->assertSame('counts-routes', $d[0]->ruleId);
        $this->assertSame('routes', $d[0]->file);
    }

    public function test_isolates_rule_exceptions(): void
    {
        $boom = new class implements ManifestRule {
            public function id(): string { return 'boom'; }
            public function title(): string { return 'boom'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'r'; }
            public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void
            {
                throw new \RuntimeException('rota');
            }
        };

        $this->assertSame([], (new ManifestEngine([$boom]))->inspect($this->manifest()));
    }
}
