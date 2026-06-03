<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Security;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Security\NoHardcodedCredentials;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoHardcodedCredentialsTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoHardcodedCredentials()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_hardcoded_password_variable(): void
    {
        $d = $this->analyze("<?php \$password = 'hunter2secret';");
        $this->assertCount(1, $d);
        $this->assertSame('no-hardcoded-credentials', $d[0]->ruleId);
    }

    public function test_flags_api_key_property(): void
    {
        $d = $this->analyze("<?php \$this->apiKey = 'sk_live_abc123';");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_env_call(): void
    {
        $d = $this->analyze("<?php \$password = env('DB_PASSWORD');");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_non_secret_variable(): void
    {
        $d = $this->analyze("<?php \$name = 'Paulo';");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_empty_string(): void
    {
        $d = $this->analyze("<?php \$password = '';");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_whitespace_only(): void
    {
        $d = $this->analyze("<?php \$token = ' ';");
        $this->assertCount(0, $d);
    }
}
