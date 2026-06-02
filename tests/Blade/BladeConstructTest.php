<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use PHPUnit\Framework\TestCase;

final class BladeConstructTest extends TestCase
{
    public function test_holds_its_data(): void
    {
        $c = new BladeConstruct(BladeConstructKind::RawEcho, '$html', 4);
        $this->assertSame(BladeConstructKind::RawEcho, $c->kind);
        $this->assertSame('$html', $c->expression);
        $this->assertSame(4, $c->line);
    }
}
