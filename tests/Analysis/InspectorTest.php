<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Analysis;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Runtime\ManifestExtractor;
use PHPUnit\Framework\TestCase;

final class InspectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-inspector-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_static_inspection_returns_score_and_diagnostics(): void
    {
        $result = (new Inspector())->inspect($this->root);

        $ids = array_map(fn ($d) => $d->ruleId, $result->diagnostics);
        $this->assertContains('no-env-outside-config', $ids);
        $this->assertLessThan(100, $result->score->score);
        $this->assertTrue($result->hasError());
        $this->assertFalse($result->bootFailed);
    }

    public function test_boot_merges_runtime_and_flags_failure(): void
    {
        $json = '{"routes":[{"uri":"u","methods":["POST"],"middleware":["web"],"action":"A@s"}],"config":{}}';
        $ok = new Inspector(new ManifestExtractor(fn ($d) => [0, $json]));
        $result = $ok->inspect($this->root, true);
        $ids = array_map(fn ($d) => $d->ruleId, $result->diagnostics);
        $this->assertContains('no-route-without-auth', $ids);
        $this->assertFalse($result->bootFailed);

        $fail = new Inspector(new ManifestExtractor(fn ($d) => [1, '']));
        $this->assertTrue($fail->inspect($this->root, true)->bootFailed);
    }
}
