<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Diagnostics;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Pipeline;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    private function d(string $rule, string $cat, Severity $sev, string $file, int $line): Diagnostic
    {
        return new Diagnostic($rule, $cat, $sev, $file, $line, 'msg', 'rec');
    }

    public function test_dedupes_same_rule_file_line(): void
    {
        $out = (new Pipeline())->process([
            $this->d('r1', Categories::SECURITY, Severity::Error, 'a.php', 10),
            $this->d('r1', Categories::SECURITY, Severity::Error, 'a.php', 10),
        ]);
        $this->assertCount(1, $out);
    }

    public function test_orders_by_severity_then_category(): void
    {
        $out = (new Pipeline())->process([
            $this->d('arch', Categories::ARCHITECTURE, Severity::Warning, 'a.php', 1),
            $this->d('sec', Categories::SECURITY, Severity::Error, 'a.php', 5),
            $this->d('perf', Categories::PERFORMANCE, Severity::Error, 'a.php', 2),
        ]);

        // Error/security primero; entre los dos Error, security (4) > performance (3).
        $this->assertSame('sec', $out[0]->ruleId);
        $this->assertSame('perf', $out[1]->ruleId);
        $this->assertSame('arch', $out[2]->ruleId);
    }
}
