<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Rules\Architecture\NoBusinessLogicInRouteClosure;
use LaravelDoctor\Rules\Architecture\NoFatControllerMethod;
use LaravelDoctor\Rules\Architecture\PreferFormRequestValidation;
use LaravelDoctor\Rules\Eloquent\NoSaveInLoopWithoutTransaction;
use LaravelDoctor\Rules\Performance\NoAllThenFilter;
use LaravelDoctor\Rules\Performance\NoQueryInLoop;
use LaravelDoctor\Rules\Performance\PreferExistsOverCount;
use LaravelDoctor\Rules\Security\NoEnvOutsideConfig;
use LaravelDoctor\Rules\Security\NoMassAssignmentGuardedEmpty;
use LaravelDoctor\Rules\Security\NoRawSqlInterpolation;

final class RuleRegistry
{
    /**
     * @return Rule[]
     */
    public static function all(): array
    {
        return [
            new NoEnvOutsideConfig(),
            new NoMassAssignmentGuardedEmpty(),
            new NoRawSqlInterpolation(),
            new PreferExistsOverCount(),
            new NoQueryInLoop(),
            new NoAllThenFilter(),
            new NoSaveInLoopWithoutTransaction(),
            new NoFatControllerMethod(),
            new PreferFormRequestValidation(),
            new NoBusinessLogicInRouteClosure(),
        ];
    }
}
