<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Scanner;

use LaravelDoctor\Scanner\FileScanner;
use PHPUnit\Framework\TestCase;

final class FileScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-scan-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        mkdir($this->root . '/vendor/foo', 0777, true);
        mkdir($this->root . '/tests', 0777, true);
        file_put_contents($this->root . '/app/User.php', "<?php\n");
        file_put_contents($this->root . '/app/notes.txt', "hola");
        file_put_contents($this->root . '/vendor/foo/Skip.php', "<?php\n");
        file_put_contents($this->root . '/tests/UserTest.php', "<?php\n");
        mkdir($this->root . '/resources/views', 0777, true);
        file_put_contents($this->root . '/resources/views/home.blade.php', "<div></div>\n");

        // Snapshots del editor (VS Code Local History) y vendor anidado: ruido a ignorar.
        mkdir($this->root . '/.history/app', 0777, true);
        file_put_contents($this->root . '/.history/app/User_20250101.php', "<?php\n");
        mkdir($this->root . '/packages/foo/vendor/bar', 0777, true);
        file_put_contents($this->root . '/packages/foo/vendor/bar/Dep.php', "<?php\n");
        mkdir($this->root . '/packages/foo/src', 0777, true);
        file_put_contents($this->root . '/packages/foo/src/Real.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_finds_php_files_and_excludes_vendor_and_non_php(): void
    {
        $files = (new FileScanner())->scan($this->root);
        $paths = array_map(fn ($f) => $f->path, $files);

        $this->assertContains($this->root . '/app/User.php', $paths);
        $this->assertNotContains($this->root . '/vendor/foo/Skip.php', $paths);
        $this->assertNotContains($this->root . '/app/notes.txt', $paths);
        $this->assertNotContains($this->root . '/tests/UserTest.php', $paths);
    }

    public function test_excludes_hidden_dirs_and_nested_vendor(): void
    {
        $files = (new FileScanner())->scan($this->root);
        $paths = array_map(fn ($f) => $f->path, $files);

        // Snapshots de editor (cualquier carpeta oculta) no son código fuente.
        $this->assertNotContains($this->root . '/.history/app/User_20250101.php', $paths);
        // vendor anidado (monorepo) también se ignora, no solo el de la raíz.
        $this->assertNotContains($this->root . '/packages/foo/vendor/bar/Dep.php', $paths);
        // ...pero el código real del paquete sí se analiza.
        $this->assertContains($this->root . '/packages/foo/src/Real.php', $paths);
    }

    public function test_source_file_carries_contents(): void
    {
        $files = (new FileScanner())->scan($this->root);
        $user = array_values(array_filter($files, fn ($f) => str_ends_with($f->path, 'User.php')))[0];
        $this->assertStringContainsString('<?php', $user->contents);
    }

    public function test_collects_blade_files_tagged_as_blade(): void
    {
        $files = (new \LaravelDoctor\Scanner\FileScanner())->scan($this->root);
        $blade = array_values(array_filter($files, fn ($f) => str_ends_with($f->path, '.blade.php')));

        $this->assertCount(1, $blade);
        $this->assertSame(\LaravelDoctor\Scanner\SourceType::Blade, $blade[0]->type);
    }

    public function test_plain_php_tagged_as_php(): void
    {
        $files = (new \LaravelDoctor\Scanner\FileScanner())->scan($this->root);
        $user = array_values(array_filter($files, fn ($f) => str_ends_with($f->path, 'User.php')))[0];

        $this->assertSame(\LaravelDoctor\Scanner\SourceType::Php, $user->type);
    }
}
