<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final readonly class Diagnostic
{
    public function __construct(
        public string $ruleId,
        public string $category,
        public Severity $severity,
        public string $file,
        public int $line,
        public string $message,
        public string $recommendation,
    ) {
    }
}
