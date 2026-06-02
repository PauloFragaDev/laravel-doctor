<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Rules\Blade\NoLogicInBlade;
use LaravelDoctor\Rules\Blade\NoUnescapedBladeOutput;

final class BladeRuleRegistry
{
    /**
     * @return BladeRule[]
     */
    public static function all(): array
    {
        return [
            new NoUnescapedBladeOutput(),
            new NoLogicInBlade(),
        ];
    }
}
