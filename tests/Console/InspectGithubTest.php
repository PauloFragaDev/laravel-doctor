<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InspectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InspectGithubTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-gh-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_github_format_emits_annotations(): void
    {
        $tester = new CommandTester(new InspectCommand());
        $exit = $tester->execute(['path' => $this->root, '--github' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('::error file=', $display);
        $this->assertStringContainsString('title=no-env-outside-config::', $display);
        $this->assertSame(1, $exit);
    }
}
