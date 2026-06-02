<?php

declare(strict_types=1);

namespace LaravelDoctor\Score;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;

final class ScoreCalculator
{
    private const DECAY = 0.6;

    /**
     * @param Diagnostic[] $diagnostics
     */
    public function score(array $diagnostics): ScoreResult
    {
        // Agrupa por regla, recordando un diagnóstico de muestra para los pesos.
        $counts = [];
        $sample = [];
        foreach ($diagnostics as $d) {
            $counts[$d->ruleId] = ($counts[$d->ruleId] ?? 0) + 1;
            $sample[$d->ruleId] ??= $d;
        }

        $penalty = 0.0;
        foreach ($counts as $ruleId => $n) {
            $d = $sample[$ruleId];
            $unit = $d->severity->weight() * Categories::weight($d->category);
            $penalty += $unit * (1 - self::DECAY ** $n) / (1 - self::DECAY);
        }

        $score = (int) max(0, round(100 - $penalty));

        return new ScoreResult($score, $this->label($score));
    }

    private function label(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Healthy',
            $score >= 70 => 'Needs work',
            $score >= 40 => 'At risk',
            default => 'Critical',
        };
    }
}
