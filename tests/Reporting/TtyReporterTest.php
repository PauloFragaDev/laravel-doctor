<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Reporting;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Score\ScoreResult;
use PHPUnit\Framework\TestCase;

final class TtyReporterTest extends TestCase
{
    public function test_includes_score_and_each_diagnostic(): void
    {
        $out = (new TtyReporter())->report(
            new ScoreResult(88, 'Needs work'),
            [new Diagnostic('no-env-outside-config', Categories::SECURITY, Severity::Error, 'app/X.php', 12, 'env() fuera de config', 'usa config()')],
        );

        $this->assertStringContainsString('88', $out);
        $this->assertStringContainsString('Needs work', $out);
        $this->assertStringContainsString('app/X.php:12', $out);
        $this->assertStringContainsString('no-env-outside-config', $out);
        $this->assertStringContainsString('env() fuera de config', $out);
    }

    public function test_clean_report_when_no_diagnostics(): void
    {
        $out = (new TtyReporter())->report(new ScoreResult(100, 'Healthy'), []);
        $this->assertStringContainsString('100', $out);
        $this->assertStringContainsString('No se encontraron problemas', $out);
    }
}
