<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

use LaravelDoctor\Rules\Runtime\NoDebugInProduction;
use LaravelDoctor\Rules\Runtime\NoMissingCastsForJson;
use LaravelDoctor\Rules\Runtime\NoRouteWithoutAuth;
use LaravelDoctor\Rules\Runtime\NoUnindexedForeignKey;

final class ManifestRuleRegistry
{
    /**
     * @return ManifestRule[]
     */
    public static function all(): array
    {
        return [
            new NoRouteWithoutAuth(),
            new NoDebugInProduction(),
            new NoMissingCastsForJson(),
            new NoUnindexedForeignKey(),
        ];
    }
}
