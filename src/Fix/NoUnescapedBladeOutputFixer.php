<?php

declare(strict_types=1);

namespace LaravelDoctor\Fix;

use LaravelDoctor\Scanner\SourceType;

/**
 * Arregla `{!! $x !!}` → `{{ $x }}` (escapado) cuando la expresión contiene una variable.
 * Solo toca echos sin escapar con `$`; los literales (HTML estático) se dejan.
 */
final class NoUnescapedBladeOutputFixer implements Fixer
{
    public function ruleId(): string
    {
        return 'no-unescaped-blade-output';
    }

    public function fix(string $contents, SourceType $type): ?string
    {
        if ($type !== SourceType::Blade) {
            return null;
        }

        $changed = false;
        $result = preg_replace_callback(
            '/\{!!(.+?)!!\}/s',
            static function (array $m) use (&$changed): string {
                if (!str_contains($m[1], '$')) {
                    return $m[0];
                }
                $changed = true;

                return '{{' . $m[1] . '}}';
            },
            $contents,
        );

        return ($changed && $result !== null) ? $result : null;
    }
}
