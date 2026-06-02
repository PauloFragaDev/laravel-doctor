<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Diagnostics;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class DiagnosticTest extends TestCase
{
    public function test_holds_its_data(): void
    {
        $d = new Diagnostic(
            ruleId: 'no-env-outside-config',
            category: 'security',
            severity: Severity::Error,
            file: 'app/Foo.php',
            line: 12,
            message: 'env() fuera de config',
            recommendation: 'usa config()',
        );

        $this->assertSame('no-env-outside-config', $d->ruleId);
        $this->assertSame('security', $d->category);
        $this->assertSame(Severity::Error, $d->severity);
        $this->assertSame(12, $d->line);
    }

    public function test_severity_weight(): void
    {
        $this->assertSame(3, Severity::Error->weight());
        $this->assertSame(2, Severity::Warning->weight());
        $this->assertSame(1, Severity::Info->weight());
    }
}
