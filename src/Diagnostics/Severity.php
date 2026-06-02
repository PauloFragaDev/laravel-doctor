<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function weight(): int
    {
        return match ($this) {
            Severity::Error => 3,
            Severity::Warning => 2,
            Severity::Info => 1,
        };
    }
}
