<?php

declare(strict_types=1);

namespace LaravelDoctor\Analysis;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

final readonly class InspectionResult
{
    /**
     * @param Diagnostic[] $diagnostics
     */
    public function __construct(
        public ScoreResult $score,
        public array $diagnostics,
        public bool $bootFailed,
    ) {
    }

    public function hasError(): bool
    {
        foreach ($this->diagnostics as $d) {
            if ($d->severity === \LaravelDoctor\Diagnostics\Severity::Error) {
                return true;
            }
        }

        return false;
    }
}
