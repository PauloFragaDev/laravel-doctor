<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Analysis;

use LaravelDoctor\Analysis\Inspector;
use PHPUnit\Framework\TestCase;

final class InspectorSuppressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-suppr-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_inline_comment_suppresses_finding(): void
    {
        file_put_contents(
            $this->root . '/app/Pay.php',
            "<?php\n// laravel-doctor-disable-next-line no-env-outside-config\n\$k = env('X');\n",
        );

        $result = (new Inspector())->inspect($this->root);
        $ids = array_map(fn ($d) => $d->ruleId, $result->diagnostics);

        $this->assertNotContains('no-env-outside-config', $ids);
        $this->assertSame(100, $result->score->score);
    }

    public function test_finding_remains_without_comment(): void
    {
        file_put_contents($this->root . '/app/Pay.php', "<?php\n\$k = env('X');\n");

        $ids = array_map(fn ($d) => $d->ruleId, (new Inspector())->inspect($this->root)->diagnostics);
        $this->assertContains('no-env-outside-config', $ids);
    }
}
