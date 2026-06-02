<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

use LaravelDoctor\Rules\Runtime\NoRouteWithoutAuth;

final class ManifestRuleRegistry
{
    /**
     * @return ManifestRule[]
     */
    public static function all(): array
    {
        return [
            new NoRouteWithoutAuth(),
        ];
    }
}
