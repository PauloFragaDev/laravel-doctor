<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Security;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Security\NoRawSqlInterpolation;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoRawSqlInterpolationTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoRawSqlInterpolation()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_interpolated_string(): void
    {
        $d = $this->analyze("<?php \$q->whereRaw(\"a = \$id\");");
        $this->assertCount(1, $d);
        $this->assertSame('no-raw-sql-interpolation', $d[0]->ruleId);
    }

    public function test_flags_concatenation_with_variable(): void
    {
        $d = $this->analyze("<?php \$q->whereRaw('a = ' . \$id);");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_literal_string(): void
    {
        $d = $this->analyze("<?php \$q->whereRaw('a = 1');");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_db_raw_literal(): void
    {
        $d = $this->analyze("<?php DB::raw('count(*)');");
        $this->assertCount(0, $d);
    }
}
