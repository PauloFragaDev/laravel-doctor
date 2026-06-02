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
    private function d(string $rule, string $cat, string $file, Severity $sev = Severity::Error): Diagnostic
    {
        return new Diagnostic($rule, $cat, $sev, $file, 12, 'm', 'r');
    }

    public function test_renders_header_groups_and_relative_paths(): void
    {
        $out = (new ResultsRenderer())->render('shop', '/var/www/html/shop', new ScoreResult(74, 'Needs work'), [
            $this->d('no-env-outside-config', Categories::SECURITY, '/var/www/html/shop/app/Pay.php'),
            $this->d('no-query-in-loop', Categories::PERFORMANCE, '/var/www/html/shop/app/List.php', Severity::Warning),
        ]);

        $this->assertStringContainsString('shop', $out);
        $this->assertStringContainsString('74/100', $out);
        $this->assertStringContainsString('SEGURIDAD', $out);
        $this->assertStringContainsString('PERFORMANCE', $out);
        $this->assertStringContainsString('no-env-outside-config', $out);
        // Ruta relativa al proyecto, no la absoluta.
        $this->assertStringContainsString('app/Pay.php:12', $out);
        $this->assertStringNotContainsString('/var/www/html/shop/app/Pay.php', $out);
    }

    public function test_location_is_relative(): void
    {
        $renderer = new ResultsRenderer();
        $d = $this->d('r', Categories::SECURITY, '/var/www/html/shop/app/Pay.php');
        $this->assertSame('app/Pay.php:12', $renderer->location($d, '/var/www/html/shop'));
    }

    public function test_clean_run(): void
    {
        $out = (new ResultsRenderer())->render('shop', '/p/shop', new ScoreResult(100, 'Healthy'), []);
        $this->assertStringContainsString('Sin hallazgos', $out);
    }
}
