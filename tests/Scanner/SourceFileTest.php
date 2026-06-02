<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Scanner;

use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class SourceFileTest extends TestCase
{
    public function test_defaults_to_php_type(): void
    {
        $f = new SourceFile('app/X.php', '<?php');
        $this->assertSame(SourceType::Php, $f->type);
    }

    public function test_accepts_blade_type(): void
    {
        $f = new SourceFile('resources/views/x.blade.php', '<div></div>', SourceType::Blade);
        $this->assertSame(SourceType::Blade, $f->type);
    }
}
