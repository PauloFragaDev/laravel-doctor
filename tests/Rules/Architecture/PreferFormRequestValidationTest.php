<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Architecture;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Architecture\PreferFormRequestValidation;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class PreferFormRequestValidationTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new PreferFormRequestValidation()]))->inspect([new SourceFile('app/Http/Controllers/X.php', $code)]);
    }

    public function test_flags_inline_request_validate(): void
    {
        $d = $this->analyze("<?php \$request->validate(['name' => 'required']);");
        $this->assertCount(1, $d);
        $this->assertSame('prefer-form-request-validation', $d[0]->ruleId);
    }

    public function test_does_not_flag_validate_on_other_var(): void
    {
        $d = $this->analyze("<?php \$validator->validate();");
        $this->assertCount(0, $d);
    }
}
