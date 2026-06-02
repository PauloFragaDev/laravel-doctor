<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Performance;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Performance\NoAllThenFilter;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoAllThenFilterTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoAllThenFilter()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_all_then_filter(): void
    {
        $d = $this->analyze("<?php User::all()->filter(fn (\$u) => \$u->active);");
        $this->assertCount(1, $d);
        $this->assertSame('no-all-then-filter', $d[0]->ruleId);
    }

    public function test_flags_all_then_where(): void
    {
        $d = $this->analyze("<?php \$c->all()->where('active', true);");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_plain_all(): void
    {
        $d = $this->analyze("<?php User::all();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_filter_on_query_result(): void
    {
        $d = $this->analyze("<?php User::query()->get()->filter(fn (\$u) => \$u->active);");
        $this->assertCount(0, $d);
    }
}
