<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Config;

use LaravelDoctor\Config\InlineSuppressions;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class InlineSuppressionsTest extends TestCase
{
    private function diag(string $rule, string $file, int $line): Diagnostic
    {
        return new Diagnostic($rule, Categories::SECURITY, Severity::Error, $file, $line, 'm', 'r');
    }

    public function test_same_line_disable_all(): void
    {
        $contents = ['a.php' => "<?php\n\$k = env('X'); // laravel-doctor-disable-line\n"];
        $out = (new InlineSuppressions())->filter([$this->diag('no-env-outside-config', 'a.php', 2)], $contents);
        $this->assertCount(0, $out);
    }

    public function test_next_line_disable_specific_rule(): void
    {
        $contents = ['a.php' => "<?php\n// laravel-doctor-disable-next-line no-env-outside-config\n\$k = env('X');\n"];
        $out = (new InlineSuppressions())->filter([$this->diag('no-env-outside-config', 'a.php', 3)], $contents);
        $this->assertCount(0, $out);
    }

    public function test_specific_rule_does_not_suppress_other_rule(): void
    {
        $contents = ['a.php' => "<?php\n\$k = env('X'); // laravel-doctor-disable-line some-other-rule\n"];
        $out = (new InlineSuppressions())->filter([$this->diag('no-env-outside-config', 'a.php', 2)], $contents);
        $this->assertCount(1, $out);
    }

    public function test_keeps_diagnostics_without_comment(): void
    {
        $contents = ['a.php' => "<?php\n\$k = env('X');\n"];
        $out = (new InlineSuppressions())->filter([$this->diag('no-env-outside-config', 'a.php', 2)], $contents);
        $this->assertCount(1, $out);
    }

    public function test_ignores_runtime_diagnostics_without_line(): void
    {
        $out = (new InlineSuppressions())->filter([$this->diag('no-route-without-auth', 'routes', 0)], []);
        $this->assertCount(1, $out);
    }

    public function test_works_with_blade_comment(): void
    {
        $contents = ['v.blade.php' => "<div>{!! \$x !!}</div> {{-- laravel-doctor-disable-line --}}\n"];
        $out = (new InlineSuppressions())->filter([$this->diag('no-unescaped-blade-output', 'v.blade.php', 1)], $contents);
        $this->assertCount(0, $out);
    }
}
