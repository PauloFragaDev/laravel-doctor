<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Performance;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Performance\NoQueryInLoop;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoQueryInLoopTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoQueryInLoop()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_query_inside_foreach(): void
    {
        // En User::query()->find() el `find` es un MethodCall (no StaticCall),
        // que es lo que la regla detecta.
        $d = $this->analyze("<?php foreach (\$ids as \$id) { \$u = User::query()->find(\$id); }");
        $this->assertCount(1, $d);
        $this->assertSame('no-query-in-loop', $d[0]->ruleId);
    }

    public function test_does_not_flag_query_outside_loop(): void
    {
        $d = $this->analyze("<?php \$u = User::query()->get();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_non_query_in_loop(): void
    {
        $d = $this->analyze("<?php foreach (\$users as \$u) { echo \$u->name; }");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_collection_first_on_relation(): void
    {
        // $membership->users->first(...) es un first() de Colección en memoria, no una query.
        $d = $this->analyze("<?php foreach (\$ms as \$m) { \$x = \$m->users->first(fn (\$u) => \$u->active); }");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_plain_variable_get(): void
    {
        // $request->get('x') no es una query Eloquent.
        $d = $this->analyze("<?php foreach (\$items as \$i) { \$v = \$request->get('x'); }");
        $this->assertCount(0, $d);
    }
}

