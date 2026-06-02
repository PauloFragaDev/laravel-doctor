<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Tui;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreResult;
use LaravelDoctor\Tui\ResultsRenderer;
use PHPUnit\Framework\TestCase;

final class ResultsRendererTest extends TestCase
{
    private function d(string $rule, string $cat, Severity $sev = Severity::Error): Diagnostic
    {
        return new Diagnostic($rule, $cat, $sev, 'app/Pay.php', 12, 'm', 'r');
    }

    public function test_renders_header_groups_and_findings(): void
    {
        $out = (new ResultsRenderer())->render('shop', new ScoreResult(74, 'Needs work'), [
            $this->d('no-env-outside-config', Categories::SECURITY),
            $this->d('no-query-in-loop', Categories::PERFORMANCE, Severity::Warning),
        ]);

        $this->assertStringContainsString('shop', $out);
        $this->assertStringContainsString('Score', $out);
        $this->assertStringContainsString('74/100', $out);
        $this->assertStringContainsString('SEGURIDAD', $out);
        $this->assertStringContainsString('PERFORMANCE', $out);
        $this->assertStringContainsString('no-env-outside-config', $out);
        $this->assertStringContainsString('app/Pay.php:12', $out);
    }

    public function test_clean_run(): void
    {
        $out = (new ResultsRenderer())->render('shop', new ScoreResult(100, 'Healthy'), []);
        $this->assertStringContainsString('Sin hallazgos', $out);
    }
}
