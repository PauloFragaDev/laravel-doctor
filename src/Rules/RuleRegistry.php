<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Rules\Architecture\PreferFormRequestValidation;
use LaravelDoctor\Rules\Eloquent\NoSaveInLoopWithoutTransaction;
use LaravelDoctor\Rules\Performance\PreferExistsOverCount;
use LaravelDoctor\Rules\Security\NoEnvOutsideConfig;

final class RuleRegistry
{
    /**
     * @return Rule[]
     */
    public static function all(): array
    {
        return [
            new NoEnvOutsideConfig(),
            new PreferExistsOverCount(),
            new NoSaveInLoopWithoutTransaction(),
            new PreferFormRequestValidation(),
        ];
    }
}
