<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

final readonly class BladeConstruct
{
    public function __construct(
        public BladeConstructKind $kind,
        public string $expression,
        public int $line,
    ) {
    }
}
