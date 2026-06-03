<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Config;

use LaravelDoctor\Config\Baseline;
use LaravelDoctor\Config\BaselineStorage;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class BaselineStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ld-baseline-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function d(string $rule, string $file): Diagnostic
    {
        return new Diagnostic($rule, Categories::SECURITY, Severity::Error, $file, 1, 'm', 'r');
    }

    public function test_write_then_load_roundtrip_with_relative_paths_and_counts(): void
    {
        $total = (new BaselineStorage())->write($this->dir, [
            $this->d('no-env-outside-config', $this->dir . '/app/Pay.php'),
            $this->d('no-env-outside-config', $this->dir . '/app/Pay.php'),
            $this->d('no-query-in-loop', $this->dir . '/app/List.php'),
        ]);

        $this->assertSame(3, $total);
        $this->assertFileExists($this->dir . '/doctor.baseline.json');

        $baseline = (new BaselineStorage())->load($this->dir);
        $this->assertNotNull($baseline);
        $this->assertSame(2, $baseline->counts[Baseline::key('no-env-outside-config', 'app/Pay.php')]);
        $this->assertSame(1, $baseline->counts[Baseline::key('no-query-in-loop', 'app/List.php')]);

        // Las rutas se guardan relativas, no absolutas.
        $this->assertStringNotContainsString($this->dir, file_get_contents($this->dir . '/doctor.baseline.json'));
    }

    public function test_load_returns_null_when_absent(): void
    {
        $this->assertNull((new BaselineStorage())->load($this->dir));
    }
}
