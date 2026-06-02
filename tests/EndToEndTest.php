<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests;

use LaravelDoctor\Console\InspectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class EndToEndTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-e2e-' . uniqid();
        mkdir($this->root . '/app/Http/Controllers', 0777, true);
        mkdir($this->root . '/config', 0777, true);

        // Dispara no-env-outside-config (error) y prefer-form-request-validation (info).
        file_put_contents($this->root . '/app/Http/Controllers/PayController.php',
            "<?php\nclass PayController {\n  public function store(\$request) {\n    \$k = env('STRIPE');\n    \$request->validate(['a' => 'required']);\n  }\n}\n");
        // env() dentro de config NO debe dispararse.
        file_put_contents($this->root . '/config/services.php', "<?php return ['k' => env('STRIPE')];");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_full_run_produces_expected_findings_and_score(): void
    {
        $tester = new CommandTester(new InspectCommand());
        $exit = $tester->execute(['path' => $this->root, '--json' => true]);

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_map(fn ($d) => $d['id'], $data['diagnostics']);

        $this->assertContains('no-env-outside-config', $ids);
        $this->assertContains('prefer-form-request-validation', $ids);
        $this->assertSame(1, $exit);              // hay un error → exit 1
        $this->assertLessThan(100, $data['score']);
        // El env() dentro de config no cuenta: solo 1 hallazgo de esa regla.
        $this->assertCount(1, array_filter($ids, fn ($id) => $id === 'no-env-outside-config'));
    }
}
