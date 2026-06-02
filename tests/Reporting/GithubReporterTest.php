<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Reporting;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Reporting\GithubReporter;
use LaravelDoctor\Score\ScoreResult;
use PHPUnit\Framework\TestCase;

final class GithubReporterTest extends TestCase
{
    public function test_emits_error_and_warning_annotations(): void
    {
        $out = (new GithubReporter())->report(
            new ScoreResult(74, 'Needs work'),
            [
                new Diagnostic('no-env-outside-config', Categories::SECURITY, Severity::Error, 'app/X.php', 12, 'msg', 'rec'),
                new Diagnostic('no-query-in-loop', Categories::PERFORMANCE, Severity::Warning, 'app/Y.php', 4, 'm2', 'r2'),
            ],
        );

        $this->assertStringContainsString('::error file=app/X.php,line=12,title=no-env-outside-config::', $out);
        $this->assertStringContainsString('::warning file=app/Y.php,line=4,title=no-query-in-loop::', $out);
        $this->assertStringContainsString('::notice title=laravel-doctor::Score 74/100', $out);
    }

    public function test_escapes_newlines_in_message(): void
    {
        $out = (new GithubReporter())->report(
            new ScoreResult(100, 'Healthy'),
            [new Diagnostic('r', Categories::SECURITY, Severity::Error, 'a.php', 1, "line1\nline2", 'rec')],
        );
        $this->assertStringNotContainsString("line1\nline2", $out);
        $this->assertStringContainsString('line1%0Aline2', $out);
    }
}
