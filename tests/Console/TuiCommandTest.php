<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\TuiCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class TuiCommandTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/ld-tui-' . uniqid();
        mkdir($this->base . '/shop/app', 0777, true);
        file_put_contents($this->base . '/shop/artisan', "#!/usr/bin/env php\n");
        file_put_contents($this->base . '/shop/app/Pay.php', "<?php\n\$k = env('X');\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));
    }

    private function command(): TuiCommand
    {
        $command = new TuiCommand();
        (new Application())->add($command);

        return $command;
    }

    public function test_requires_interactive_terminal(): void
    {
        // Forzamos no interactivo para no lanzar el bucle full-screen (que leería de STDIN).
        $tester = new CommandTester($this->command());
        $exit = $tester->execute(['--base' => $this->base], ['interactive' => false]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('requiere una terminal interactiva', $tester->getDisplay());
    }

    public function test_reports_when_no_projects(): void
    {
        $empty = sys_get_temp_dir() . '/ld-tui-empty-' . uniqid();
        mkdir($empty);
        $tester = new CommandTester($this->command());

        $exit = $tester->execute(['--base' => $empty], ['interactive' => false]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No se encontraron proyectos', $tester->getDisplay());
        rmdir($empty);
    }
}
