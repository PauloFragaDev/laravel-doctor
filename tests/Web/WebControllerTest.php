<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Web;

use LaravelDoctor\Web\WebController;
use PHPUnit\Framework\TestCase;

final class WebControllerTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/ld-web-' . uniqid();
        mkdir($this->base . '/shop/app', 0777, true);
        file_put_contents($this->base . '/shop/artisan', "#!/usr/bin/env php\n");
        file_put_contents($this->base . '/shop/app/Pay.php', "<?php\n\$k = env('STRIPE');\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));
    }

    public function test_projects_lists_laravel_apps(): void
    {
        $projects = (new WebController($this->base))->projects();
        $names = array_map(fn ($p) => $p['name'], $projects);
        $this->assertContains('shop', $names);
    }

    public function test_inspect_returns_score_and_diagnostics_with_snippet(): void
    {
        $data = (new WebController($this->base))->inspect($this->base . '/shop', false);

        $this->assertLessThan(100, $data['score']);
        $ids = array_map(fn ($d) => $d['id'], $data['diagnostics']);
        $this->assertContains('no-env-outside-config', $ids);

        $envFinding = array_values(array_filter($data['diagnostics'], fn ($d) => $d['id'] === 'no-env-outside-config'))[0];
        $this->assertStringContainsString("env('STRIPE')", $envFinding['snippet']);
    }

    public function test_inspect_rejects_path_outside_base(): void
    {
        $data = (new WebController($this->base))->inspect('/etc', false);
        $this->assertArrayHasKey('error', $data);
    }

    public function test_snippet_blocks_path_traversal(): void
    {
        $this->assertNull((new WebController($this->base))->snippet('/etc/passwd', 1));
    }
}
