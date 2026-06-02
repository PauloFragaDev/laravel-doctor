<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InstallCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-install-' . uniqid();
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_installs_skill_for_claude_code(): void
    {
        $tester = new CommandTester(new InstallCommand());
        $exit = $tester->execute(['target' => $this->root]);

        $dest = $this->root . '/.claude/skills/laravel-doctor/SKILL.md';
        $this->assertSame(0, $exit);
        $this->assertFileExists($dest);
        $this->assertStringContainsString('laravel-doctor --json', file_get_contents($dest));
    }
}
