<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InspectCommand;
use LaravelDoctor\Runtime\ManifestExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InspectBootTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-boot-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        // Hallazgo estático (error) para verificar que se mezcla con los de runtime.
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_boot_merges_runtime_findings(): void
    {
        // Extractor fake que devuelve un manifiesto con una ruta POST sin auth.
        $json = '{"routes":[{"uri":"admin/users","methods":["POST"],"middleware":["web"],"action":"A@store"}],"config":{}}';
        $extractor = new ManifestExtractor(fn (string $dir) => [0, $json]);

        $tester = new CommandTester(new InspectCommand($extractor));
        $exit = $tester->execute(['path' => $this->root, '--json' => true, '--boot' => true]);

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_map(fn ($d) => $d['id'], $data['diagnostics']);

        $this->assertContains('no-env-outside-config', $ids);   // estático
        $this->assertContains('no-route-without-auth', $ids);   // runtime
        $this->assertSame(1, $exit);                            // hay un error estático
    }

    public function test_boot_degrades_to_static_when_extractor_fails(): void
    {
        // Extractor fake que falla (exit != 0) → null.
        $extractor = new ManifestExtractor(fn (string $dir) => [1, '']);

        $tester = new CommandTester(new InspectCommand($extractor));
        $exit = $tester->execute(['path' => $this->root, '--boot' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('no se pudo bootear', $display);
        $this->assertStringContainsString('no-env-outside-config', $display); // sigue el estático
        $this->assertSame(1, $exit);
    }
}
