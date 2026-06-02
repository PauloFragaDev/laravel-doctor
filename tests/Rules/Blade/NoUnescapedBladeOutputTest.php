<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Blade;

use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Rules\Blade\NoUnescapedBladeOutput;
use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class NoUnescapedBladeOutputTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new BladeEngine([new NoUnescapedBladeOutput()]))
            ->inspect([new SourceFile('resources/views/x.blade.php', $code, SourceType::Blade)]);
    }

    public function test_flags_raw_echo_with_variable(): void
    {
        $d = $this->analyze("<p>{!! \$content !!}</p>");
        $this->assertCount(1, $d);
        $this->assertSame('no-unescaped-blade-output', $d[0]->ruleId);
    }

    public function test_does_not_flag_escaped_echo(): void
    {
        $d = $this->analyze("<p>{{ \$content }}</p>");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_raw_echo_without_variable(): void
    {
        $d = $this->analyze("<p>{!! '<br>' !!}</p>");
        $this->assertCount(0, $d);
    }
}
