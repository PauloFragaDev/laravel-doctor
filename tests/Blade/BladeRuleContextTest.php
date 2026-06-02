<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class BladeRuleContextTest extends TestCase
{
    private function fakeRule(): BladeRule
    {
        return new class implements BladeRule {
            public function id(): string { return 'fake-blade-rule'; }
            public function title(): string { return 'Fake'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'arregla esto'; }
            public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void {}
        };
    }

    public function test_report_builds_diagnostic(): void
    {
        $collector = new DiagnosticCollector();
        $ctx = new BladeRuleContext($this->fakeRule(), 'resources/views/x.blade.php', $collector);

        $ctx->report(new BladeConstruct(BladeConstructKind::RawEcho, '$html', 9), 'mensaje');

        $all = $collector->all();
        $this->assertCount(1, $all);
        $this->assertSame('fake-blade-rule', $all[0]->ruleId);
        $this->assertSame('security', $all[0]->category);
        $this->assertSame(Severity::Warning, $all[0]->severity);
        $this->assertSame('resources/views/x.blade.php', $all[0]->file);
        $this->assertSame(9, $all[0]->line);
        $this->assertSame('mensaje', $all[0]->message);
        $this->assertSame('arregla esto', $all[0]->recommendation);
    }
}
