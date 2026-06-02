<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Analysis;

use LaravelDoctor\Analysis\Inspector;
use PHPUnit\Framework\TestCase;

final class InspectorConfigTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-inspcfg-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_config_disables_rule(): void
    {
        // Sin config: la regla aparece.
        $ids = array_map(fn ($d) => $d->ruleId, (new Inspector())->inspect($this->root)->diagnostics);
        $this->assertContains('no-env-outside-config', $ids);

        // Con config que la desactiva: desaparece.
        file_put_contents($this->root . '/doctor.config.php', "<?php return ['rules' => ['no-env-outside-config' => false]];");
        $result = (new Inspector())->inspect($this->root);
        $ids = array_map(fn ($d) => $d->ruleId, $result->diagnostics);

        $this->assertNotContains('no-env-outside-config', $ids);
        $this->assertSame(100, $result->score->score);
    }
}
