<?php

declare(strict_types=1);

namespace LaravelDoctor\Reporting;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

final class AgentReporter
{
    /**
     * @param Diagnostic[] $diagnostics
     */
    public function report(ScoreResult $score, array $diagnostics): string
    {
        return json_encode([
            'score' => $score->score,
            'label' => $score->label,
            'diagnostics' => array_map(fn (Diagnostic $d) => [
                'id' => $d->ruleId,
                'category' => $d->category,
                'severity' => $d->severity->value,
                'file' => $d->file,
                'line' => $d->line,
                'message' => $d->message,
                'recommendation' => $d->recommendation,
            ], $diagnostics),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
