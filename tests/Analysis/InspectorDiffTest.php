<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Analysis;

use LaravelDoctor\Analysis\Inspector;
use PHPUnit\Framework\TestCase;

final class InspectorDiffTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-diff-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        file_put_contents($this->root . '/app/Pay.php', "<?php\n\$k = env('A');\n");
        file_put_contents($this->root . '/app/Mail.php', "<?php\n\$k = env('B');\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_only_files_limits_analysis_to_changed_files(): void
    {
        // Sin filtro: los dos archivos dan hallazgo.
        $all = (new Inspector())->inspect($this->root);
        $this->assertCount(2, $all->diagnostics);

        // Con onlyFiles = solo Pay.php → solo ese hallazgo.
        $diff = (new Inspector())->inspect($this->root, false, true, [$this->root . '/app/Pay.php']);
        $this->assertCount(1, $diff->diagnostics);
        $this->assertSame($this->root . '/app/Pay.php', $diff->diagnostics[0]->file);
    }

    public function test_empty_only_files_yields_no_findings(): void
    {
        $diff = (new Inspector())->inspect($this->root, false, true, []);
        $this->assertSame([], $diff->diagnostics);
        $this->assertSame(100, $diff->score->score);
    }
}
