<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Architecture;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Architecture\NoFatControllerMethod;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoFatControllerMethodTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoFatControllerMethod()]))->inspect([new SourceFile('app/Http/Controllers/X.php', $code)]);
    }

    private function longBody(): string
    {
        return str_repeat("        \$x = 1;\n", 70);
    }

    public function test_flags_long_method_in_controller(): void
    {
        $code = "<?php\nclass UserController {\n    public function index() {\n" . $this->longBody() . "    }\n}\n";
        $d = $this->analyze($code);
        $this->assertCount(1, $d);
        $this->assertSame('no-fat-controller-method', $d[0]->ruleId);
    }

    public function test_does_not_flag_short_method_in_controller(): void
    {
        $code = "<?php\nclass UserController {\n    public function index() {\n        return 1;\n    }\n}\n";
        $d = $this->analyze($code);
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_long_method_in_non_controller(): void
    {
        $code = "<?php\nclass UserService {\n    public function index() {\n" . $this->longBody() . "    }\n}\n";
        $d = $this->analyze($code);
        $this->assertCount(0, $d);
    }
}
