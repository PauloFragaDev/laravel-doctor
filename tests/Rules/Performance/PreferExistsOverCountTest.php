<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Performance;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Performance\PreferExistsOverCount;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class PreferExistsOverCountTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new PreferExistsOverCount()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_count_greater_than_zero(): void
    {
        $d = $this->analyze("<?php if (\$user->posts()->count() > 0) {}");
        $this->assertCount(1, $d);
        $this->assertSame('prefer-exists-over-count', $d[0]->ruleId);
    }

    public function test_does_not_flag_count_used_for_value(): void
    {
        $d = $this->analyze("<?php \$n = \$user->posts()->count();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_other_comparisons(): void
    {
        $d = $this->analyze("<?php if (\$user->age > 0) {}");
        $this->assertCount(0, $d);
    }
}
