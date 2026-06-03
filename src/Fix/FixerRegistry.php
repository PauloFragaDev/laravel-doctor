<?php

declare(strict_types=1);

namespace LaravelDoctor\Fix;

final class FixerRegistry
{
    /**
     * @return Fixer[]
     */
    public static function all(): array
    {
        return [
            new PreferExistsOverCountFixer(),
            new NoUnescapedBladeOutputFixer(),
        ];
    }
}
