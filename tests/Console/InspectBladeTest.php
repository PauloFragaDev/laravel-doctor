<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InspectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InspectBladeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-blade-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        mkdir($this->root . '/resources/views', 0777, true);
        // Problema PHP (error) + problema Blade (warning) en el mismo proyecto.
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
        file_put_contents($this->root . '/resources/views/show.blade.php', "<p>{!! \$html !!}</p>");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_reports_php_and_blade_findings_together(): void
    {
        $tester = new CommandTester(new InspectCommand());
        $exit = $tester->execute(['path' => $this->root, '--json' => true]);

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_map(fn ($d) => $d['id'], $data['diagnostics']);

        $this->assertContains('no-env-outside-config', $ids);        // del motor PHP
        $this->assertContains('no-unescaped-blade-output', $ids);    // del motor Blade
        $this->assertSame(1, $exit);                                 // hay un error PHP
    }
}
