<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Blade;

use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Rules\Blade\NoLogicInBlade;
use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class NoLogicInBladeTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new BladeEngine([new NoLogicInBlade()]))
            ->inspect([new SourceFile('resources/views/x.blade.php', $code, SourceType::Blade)]);
    }

    public function test_flags_php_block_with_query(): void
    {
        $d = $this->analyze("@php\n\$users = User::all();\n@endphp");
        $this->assertCount(1, $d);
        $this->assertSame('no-logic-in-blade', $d[0]->ruleId);
    }

    public function test_does_not_flag_trivial_php_block(): void
    {
        // Un @php trivial (contador) no es lógica de negocio/DB → no se marca.
        $d = $this->analyze("@php \$i++; @endphp");
        $this->assertCount(0, $d);
    }

    public function test_flags_query_in_echo(): void
    {
        $d = $this->analyze("<ul>{{ User::all() }}</ul>");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_plain_echo(): void
    {
        $d = $this->analyze("<p>{{ \$user->name }}</p>");
        $this->assertCount(0, $d);
    }
}
