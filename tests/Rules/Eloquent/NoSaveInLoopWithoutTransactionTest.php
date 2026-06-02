<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Eloquent;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Eloquent\NoSaveInLoopWithoutTransaction;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoSaveInLoopWithoutTransactionTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoSaveInLoopWithoutTransaction()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_save_in_loop_without_transaction(): void
    {
        $d = $this->analyze("<?php foreach (\$users as \$u) { \$u->save(); }");
        $this->assertCount(1, $d);
        $this->assertSame('no-save-in-loop-without-transaction', $d[0]->ruleId);
    }

    public function test_does_not_flag_save_outside_loop(): void
    {
        $d = $this->analyze("<?php \$u->save();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_save_in_loop_within_transaction(): void
    {
        $d = $this->analyze("<?php DB::transaction(function () use (\$users) { foreach (\$users as \$u) { \$u->save(); } });");
        $this->assertCount(0, $d);
    }
}
