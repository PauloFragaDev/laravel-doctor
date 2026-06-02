<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Tui;

use LaravelDoctor\Tui\ProjectDiscovery;
use PHPUnit\Framework\TestCase;

final class ProjectDiscoveryTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/ld-disc-' . uniqid();
        mkdir($this->base . '/shop', 0777, true);
        mkdir($this->base . '/blog', 0777, true);
        mkdir($this->base . '/not-laravel', 0777, true);
        file_put_contents($this->base . '/shop/artisan', "#!/usr/bin/env php\n");
        file_put_contents($this->base . '/blog/artisan', "#!/usr/bin/env php\n");
        // not-laravel no tiene artisan.
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));
    }

    public function test_discovers_only_laravel_projects_sorted(): void
    {
        $projects = (new ProjectDiscovery())->discover($this->base);

        $names = array_map(fn ($p) => $p->name, $projects);
        $this->assertSame(['blog', 'shop'], $names);
        $this->assertStringEndsWith('/blog', $projects[0]->path);
    }

    public function test_includes_base_dir_itself_when_it_is_a_laravel_app(): void
    {
        $app = sys_get_temp_dir() . '/ld-disc-app-' . uniqid();
        mkdir($app, 0777, true);
        file_put_contents($app . '/artisan', "#!/usr/bin/env php\n");

        $projects = (new ProjectDiscovery())->discover($app);

        $this->assertCount(1, $projects);
        $this->assertSame($app, $projects[0]->path);
        exec('rm -rf ' . escapeshellarg($app));
    }

    public function test_empty_when_no_projects(): void
    {
        $empty = sys_get_temp_dir() . '/ld-disc-empty-' . uniqid();
        mkdir($empty);
        $this->assertSame([], (new ProjectDiscovery())->discover($empty));
        rmdir($empty);
    }
}
