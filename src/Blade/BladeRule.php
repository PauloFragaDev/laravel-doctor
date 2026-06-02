<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Diagnostics\Severity;

interface BladeRule
{
    public function id(): string;

    public function title(): string;

    /** Una de las constantes de LaravelDoctor\Diagnostics\Categories. */
    public function category(): string;

    public function severity(): Severity;

    public function recommendation(): string;

    public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void;
}
