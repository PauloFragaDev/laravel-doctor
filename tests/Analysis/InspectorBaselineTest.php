<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Analysis;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Config\BaselineStorage;
use PHPUnit\Framework\TestCase;

final class InspectorBaselineTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-insp-base-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        // Hallazgo existente (error): env() fuera de config.
        file_put_contents($this->root . '/app/Pay.php', "<?php\n\$k = env('X');\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_baseline_suppresses_known_finding_but_new_ones_show(): void
    {
        // Sin baseline aparece.
        $ids = fn () => array_map(fn ($d) => $d->ruleId, (new Inspector())->inspect($this->root)->diagnostics);
        $this->assertContains('no-env-outside-config', $ids());

        // Generamos baseline con lo actual y verificamos que se suprime.
        $all = (new Inspector())->inspect($this->root, false, false)->diagnostics;
        (new BaselineStorage())->write($this->root, $all);

        $this->assertNotContains('no-env-outside-config', $ids());

        // Un hallazgo NUEVO (otro env en otro archivo) sí aparece pese a la baseline.
        file_put_contents($this->root . '/app/Mail.php', "<?php\n\$k = env('Y');\n");
        $this->assertContains('no-env-outside-config', $ids());
    }

    public function test_no_baseline_flag_ignores_it(): void
    {
        $all = (new Inspector())->inspect($this->root, false, false)->diagnostics;
        (new BaselineStorage())->write($this->root, $all);

        // useBaseline=false → vuelve a aparecer aunque haya baseline.
        $ids = array_map(fn ($d) => $d->ruleId, (new Inspector())->inspect($this->root, false, false)->diagnostics);
        $this->assertContains('no-env-outside-config', $ids);
    }
}
