<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

use LaravelDoctor\Diagnostics\Severity;

final readonly class DoctorConfig
{
    /**
     * @param string[] $disabled Ids de reglas desactivadas.
     * @param array<string,Severity> $severityOverrides Id de regla => severidad forzada.
     * @param string[] $exclude Globs de rutas a ignorar (fnmatch).
     */
    public function __construct(
        public array $disabled = [],
        public array $severityOverrides = [],
        public array $exclude = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }
}
