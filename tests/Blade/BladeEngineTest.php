<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class BladeEngineTest extends TestCase
{
    private function rawEchoReporter(): BladeRule
    {
        return new class implements BladeRule {
            public function id(): string { return 'reports-raw'; }
            public function title(): string { return 'raw'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'fix'; }
            public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
            {
                if ($construct->kind === \LaravelDoctor\Blade\BladeConstructKind::RawEcho) {
                    $context->report($construct, 'raw echo');
                }
            }
        };
    }

    public function test_runs_blade_rules_over_files(): void
    {
        $engine = new BladeEngine([$this->rawEchoReporter()]);
        $d = $engine->inspect([
            new SourceFile('resources/views/x.blade.php', "{!! \$a !!}\n{{ \$b }}", SourceType::Blade),
        ]);

        $this->assertCount(1, $d);
        $this->assertSame('reports-raw', $d[0]->ruleId);
        $this->assertSame('resources/views/x.blade.php', $d[0]->file);
    }

    public function test_isolates_rule_exceptions(): void
    {
        $boom = new class implements BladeRule {
            public function id(): string { return 'boom'; }
            public function title(): string { return 'boom'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'x'; }
            public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
            {
                throw new \RuntimeException('regla rota');
            }
        };

        $engine = new BladeEngine([$boom]);
        $d = $engine->inspect([new SourceFile('x.blade.php', "{!! \$a !!}", SourceType::Blade)]);

        $this->assertSame([], $d);
    }
}
