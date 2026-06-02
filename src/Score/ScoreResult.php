<?php

declare(strict_types=1);

namespace LaravelDoctor\Score;

final readonly class ScoreResult
{
    public function __construct(
        public int $score,
        public string $label,
    ) {
    }
}
