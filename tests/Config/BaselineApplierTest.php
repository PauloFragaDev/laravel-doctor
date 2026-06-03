<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Config;

use LaravelDoctor\Config\Baseline;
use LaravelDoctor\Config\BaselineApplier;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class BaselineApplierTest extends TestCase
{
    private function d(string $rule, string $file, int $line): Diagnostic
    {
        return new Diagnostic($rule, Categories::SECURITY, Severity::Error, $file, $line, 'm', 'r');
    }

    public function test_suppresses_baselined_findings_ignoring_line(): void
    {
        $baseline = new Baseline([Baseline::key('no-env-outside-config', 'app/Pay.php') => 1]);

        // El hallazgo está en la línea 99 ahora (antes 12); igual debe suprimirse.
        $out = (new BaselineApplier())->apply(
            [$this->d('no-env-outside-config', '/proj/app/Pay.php', 99)],
            $baseline,
            '/proj',
        );

        $this->assertSame([], $out);
    }

    public function test_new_finding_beyond_count_is_kept(): void
    {
        $baseline = new Baseline([Baseline::key('no-env-outside-config', 'app/Pay.php') => 1]);

        // Dos hallazgos de la misma regla/archivo; la baseline tolera 1 → el segundo (nuevo) pasa.
        $out = (new BaselineApplier())->apply(
            [
                $this->d('no-env-outside-config', '/proj/app/Pay.php', 12),
                $this->d('no-env-outside-config', '/proj/app/Pay.php', 40),
            ],
            $baseline,
            '/proj',
        );

        $this->assertCount(1, $out);
    }

    public function test_finding_not_in_baseline_is_kept(): void
    {
        $out = (new BaselineApplier())->apply(
            [$this->d('no-query-in-loop', '/proj/app/X.php', 5)],
            new Baseline([Baseline::key('no-env-outside-config', 'app/Pay.php') => 1]),
            '/proj',
        );

        $this->assertCount(1, $out);
    }

    public function test_empty_baseline_passes_through(): void
    {
        $in = [$this->d('r', '/proj/app/X.php', 1)];
        $this->assertCount(1, (new BaselineApplier())->apply($in, Baseline::empty(), '/proj'));
    }
}
