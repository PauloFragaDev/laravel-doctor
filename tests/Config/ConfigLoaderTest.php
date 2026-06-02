<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Config;

use LaravelDoctor\Config\ConfigLoader;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ld-config-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_loads_php_config(): void
    {
        file_put_contents($this->dir . '/doctor.config.php', <<<'PHP'
        <?php
        return [
            'rules' => [
                'no-fat-controller-method' => false,
                'no-query-in-loop' => 'error',
                'bogus' => 'nope',
            ],
            'exclude' => ['app/Legacy/*'],
        ];
        PHP);

        $config = (new ConfigLoader())->load($this->dir);

        $this->assertContains('no-fat-controller-method', $config->disabled);
        $this->assertSame(Severity::Error, $config->severityOverrides['no-query-in-loop']);
        $this->assertArrayNotHasKey('bogus', $config->severityOverrides);
        $this->assertSame(['app/Legacy/*'], $config->exclude);
    }

    public function test_empty_when_no_config_file(): void
    {
        $config = (new ConfigLoader())->load($this->dir);
        $this->assertSame([], $config->disabled);
        $this->assertSame([], $config->severityOverrides);
        $this->assertSame([], $config->exclude);
    }

    public function test_loads_json_config(): void
    {
        file_put_contents($this->dir . '/doctor.config.json', '{"rules":{"no-logic-in-blade":"off"}}');
        $config = (new ConfigLoader())->load($this->dir);
        $this->assertContains('no-logic-in-blade', $config->disabled);
    }
}
