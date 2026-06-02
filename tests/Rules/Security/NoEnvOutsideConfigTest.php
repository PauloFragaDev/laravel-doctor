<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Security;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Security\NoEnvOutsideConfig;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoEnvOutsideConfigTest extends TestCase
{
    private function analyze(string $path, string $code): array
    {
        return (new Engine([new NoEnvOutsideConfig()]))->inspect([new SourceFile($path, $code)]);
    }

    public function test_flags_env_in_app_code(): void
    {
        $d = $this->analyze('app/Services/Pay.php', "<?php \$k = env('STRIPE_KEY');");
        $this->assertCount(1, $d);
        $this->assertSame('no-env-outside-config', $d[0]->ruleId);
    }

    public function test_does_not_flag_env_inside_config(): void
    {
        $d = $this->analyze('config/services.php', "<?php return ['key' => env('STRIPE_KEY')];");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_unrelated_calls(): void
    {
        $d = $this->analyze('app/Services/Pay.php', "<?php strlen('x');");
        $this->assertCount(0, $d);
    }
}
