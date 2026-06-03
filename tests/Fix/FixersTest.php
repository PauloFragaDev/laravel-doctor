<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Fix;

use LaravelDoctor\Fix\NoUnescapedBladeOutputFixer;
use LaravelDoctor\Fix\PreferExistsOverCountFixer;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class FixersTest extends TestCase
{
    public function test_count_to_exists_preserving_format(): void
    {
        $fixer = new PreferExistsOverCountFixer();
        $code = "<?php\n\nif (\$user->posts()->count() > 0) {\n    go();\n}\n";

        $fixed = $fixer->fix($code, SourceType::Php);

        $this->assertNotNull($fixed);
        $this->assertStringContainsString('$user->posts()->exists()', $fixed);
        $this->assertStringNotContainsString('count() > 0', $fixed);
        // Formato preservado (indentación y cuerpo intactos).
        $this->assertStringContainsString("    go();", $fixed);
    }

    public function test_count_fixer_returns_null_when_nothing_to_fix(): void
    {
        $this->assertNull((new PreferExistsOverCountFixer())->fix("<?php \$x = 1;", SourceType::Php));
    }

    public function test_count_fixer_ignores_blade(): void
    {
        $this->assertNull((new PreferExistsOverCountFixer())->fix("{{ \$x }}", SourceType::Blade));
    }

    public function test_blade_unescape_to_escaped(): void
    {
        $fixer = new NoUnescapedBladeOutputFixer();
        $fixed = $fixer->fix("<p>{!! \$html !!}</p>", SourceType::Blade);

        $this->assertSame("<p>{{ \$html }}</p>", $fixed);
    }

    public function test_blade_fixer_ignores_literal_and_escaped(): void
    {
        $fixer = new NoUnescapedBladeOutputFixer();
        $this->assertNull($fixer->fix("<p>{!! '<br>' !!}</p>", SourceType::Blade)); // sin variable
        $this->assertNull($fixer->fix("<p>{{ \$x }}</p>", SourceType::Blade));      // ya escapado
    }
}
