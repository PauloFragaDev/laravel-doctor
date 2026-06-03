<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\BaselineCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class BaselineCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-basecmd-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        file_put_contents($this->root . '/app/Pay.php', "<?php\n\$k = env('X');\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_writes_baseline_file_with_current_findings(): void
    {
        $tester = new CommandTester(new BaselineCommand());
        $exit = $tester->execute(['path' => $this->root]);

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->root . '/doctor.baseline.json');
        $data = json_decode((string) file_get_contents($this->root . '/doctor.baseline.json'), true);
        $rules = array_map(fn ($e) => $e['rule'], $data['entries']);
        $this->assertContains('no-env-outside-config', $rules);
        $this->assertStringContainsString('hallazgo', $tester->getDisplay());
    }
}
