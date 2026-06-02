<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Config;

use LaravelDoctor\Config\ConfigApplier;
use LaravelDoctor\Config\DoctorConfig;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class ConfigApplierTest extends TestCase
{
    private function d(string $rule, string $file, Severity $sev = Severity::Warning): Diagnostic
    {
        return new Diagnostic($rule, Categories::SECURITY, $sev, $file, 1, 'm', 'r');
    }

    public function test_drops_disabled_rules(): void
    {
        $out = (new ConfigApplier())->apply(
            [$this->d('r1', 'app/A.php'), $this->d('r2', 'app/B.php')],
            new DoctorConfig(disabled: ['r1']),
        );
        $this->assertCount(1, $out);
        $this->assertSame('r2', $out[0]->ruleId);
    }

    public function test_overrides_severity(): void
    {
        $out = (new ConfigApplier())->apply(
            [$this->d('r1', 'app/A.php', Severity::Warning)],
            new DoctorConfig(severityOverrides: ['r1' => Severity::Error]),
        );
        $this->assertSame(Severity::Error, $out[0]->severity);
    }

    public function test_excludes_by_glob(): void
    {
        $out = (new ConfigApplier())->apply(
            [$this->d('r1', 'app/Legacy/Old.php'), $this->d('r1', 'app/New.php')],
            new DoctorConfig(exclude: ['app/Legacy/*']),
        );
        $this->assertCount(1, $out);
        $this->assertSame('app/New.php', $out[0]->file);
    }

    public function test_passthrough_without_config(): void
    {
        $in = [$this->d('r1', 'app/A.php')];
        $this->assertCount(1, (new ConfigApplier())->apply($in, DoctorConfig::empty()));
    }
}
