<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InspectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InspectCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-cmd-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_json_output_and_failure_exit_on_error(): void
    {
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
        $tester = new CommandTester(new InspectCommand());

        $exit = $tester->execute(['path' => $this->root, '--json' => true]);

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('no-env-outside-config', $data['diagnostics'][0]['id']);
        $this->assertSame(1, $exit); // hay un diagnóstico de severidad error
    }

    public function test_clean_project_exits_zero(): void
    {
        file_put_contents($this->root . '/app/Ok.php', "<?php \$x = 1;");
        $tester = new CommandTester(new InspectCommand());

        $exit = $tester->execute(['path' => $this->root]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('100', $tester->getDisplay());
    }
}
