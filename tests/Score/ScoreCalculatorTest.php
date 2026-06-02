<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Score;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreCalculator;
use PHPUnit\Framework\TestCase;

final class ScoreCalculatorTest extends TestCase
{
    private function d(string $rule, string $cat, Severity $sev): Diagnostic
    {
        return new Diagnostic($rule, $cat, $sev, 'a.php', 1, 'm', 'r');
    }

    public function test_perfect_score_with_no_diagnostics(): void
    {
        $result = (new ScoreCalculator())->score([]);
        $this->assertSame(100, $result->score);
        $this->assertSame('Healthy', $result->label);
    }

    public function test_single_security_error(): void
    {
        $result = (new ScoreCalculator())->score([
            $this->d('r', Categories::SECURITY, Severity::Error),
        ]);
        $this->assertSame(88, $result->score);
        $this->assertSame('Needs work', $result->label);
    }

    public function test_same_rule_saturates(): void
    {
        // 3 ocurrencias de la misma regla: unit=12, penalty=12*(1-0.6^3)/0.4 = 12*0.784/0.4 = 23.52 → 100-23.52 = 76.48 → 76
        $result = (new ScoreCalculator())->score([
            $this->d('r', Categories::SECURITY, Severity::Error),
            $this->d('r', Categories::SECURITY, Severity::Error),
            $this->d('r', Categories::SECURITY, Severity::Error),
        ]);
        $this->assertSame(76, $result->score);
    }
}
