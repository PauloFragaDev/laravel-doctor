<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Architecture;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Architecture\NoBusinessLogicInRouteClosure;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoBusinessLogicInRouteClosureTest extends TestCase
{
    private function analyze(string $path, string $code): array
    {
        return (new Engine([new NoBusinessLogicInRouteClosure()]))->inspect([new SourceFile($path, $code)]);
    }

    private const BIG_CLOSURE = 'function () { $a=1; $b=2; $c=3; $d=4; $e=5; $f=6; }';

    public function test_flags_big_closure_in_routes_file(): void
    {
        $d = $this->analyze('routes/web.php', "<?php Route::get('/x', " . self::BIG_CLOSURE . ");");
        $this->assertCount(1, $d);
        $this->assertSame('no-business-logic-in-route-closure', $d[0]->ruleId);
    }

    public function test_does_not_flag_small_closure(): void
    {
        $d = $this->analyze('routes/web.php', "<?php Route::get('/x', function () { return 1; });");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_big_closure_outside_routes(): void
    {
        $d = $this->analyze('app/X.php', "<?php Route::get('/x', " . self::BIG_CLOSURE . ");");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_closure_not_passed_to_route(): void
    {
        $d = $this->analyze('routes/web.php', "<?php \$fn = " . self::BIG_CLOSURE . ";");
        $this->assertCount(0, $d);
    }
}
