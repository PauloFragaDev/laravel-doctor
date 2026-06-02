<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Security;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Security\NoMassAssignmentGuardedEmpty;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoMassAssignmentGuardedEmptyTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoMassAssignmentGuardedEmpty()]))->inspect([new SourceFile('app/Models/User.php', $code)]);
    }

    public function test_flags_empty_guarded(): void
    {
        $d = $this->analyze("<?php class User { protected \$guarded = []; }");
        $this->assertCount(1, $d);
        $this->assertSame('no-mass-assignment-guarded-empty', $d[0]->ruleId);
    }

    public function test_flags_unguard_call(): void
    {
        $d = $this->analyze("<?php User::unguard();");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_nonempty_guarded(): void
    {
        $d = $this->analyze("<?php class User { protected \$guarded = ['id']; }");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_empty_fillable(): void
    {
        $d = $this->analyze("<?php class User { protected \$fillable = []; }");
        $this->assertCount(0, $d);
    }
}
