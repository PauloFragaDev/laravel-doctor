<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeScanner;
use PHPUnit\Framework\TestCase;

final class BladeScannerTest extends TestCase
{
    private const TEMPLATE = "<div>\n  {{-- un comentario\n  multilinea --}}\n  {!! \$html !!}\n  {{ \$name }}\n  @php\n    \$x = 1;\n  @endphp\n</div>\n";

    public function test_extracts_each_construct_with_correct_line(): void
    {
        $constructs = (new BladeScanner())->scan(self::TEMPLATE);

        $byKind = [];
        foreach ($constructs as $c) {
            $byKind[$c->kind->name][] = $c;
        }

        $this->assertCount(1, $byKind['RawEcho']);
        $this->assertSame('$html', $byKind['RawEcho'][0]->expression);
        $this->assertSame(4, $byKind['RawEcho'][0]->line);

        $this->assertCount(1, $byKind['EscapedEcho']);
        $this->assertSame('$name', $byKind['EscapedEcho'][0]->expression);
        $this->assertSame(5, $byKind['EscapedEcho'][0]->line);

        $this->assertCount(1, $byKind['PhpBlock']);
        $this->assertSame('$x = 1;', $byKind['PhpBlock'][0]->expression);
        $this->assertSame(6, $byKind['PhpBlock'][0]->line);
    }

    public function test_ignores_comments(): void
    {
        // El comentario contiene texto que NO debe convertirse en constructs.
        $constructs = (new BladeScanner())->scan("{{-- {{ \$x }} --}}\n");
        $this->assertSame([], $constructs);
    }
}
