<?php

declare(strict_types=1);

namespace LaravelDoctor\Reporting;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

final class TtyReporter
{
    /**
     * @param Diagnostic[] $diagnostics
     */
    public function report(ScoreResult $score, array $diagnostics): string
    {
        $lines = [];
        $lines[] = sprintf('laravel-doctor — Score: %d/100 (%s)', $score->score, $score->label);
        $lines[] = '';

        if ($diagnostics === []) {
            $lines[] = 'No se encontraron problemas.';

            return implode("\n", $lines) . "\n";
        }

        foreach ($diagnostics as $d) {
            $lines[] = sprintf('[%s] %s:%d  %s', strtoupper($d->severity->value), $d->file, $d->line, $d->ruleId);
            $lines[] = '    ' . $d->message;
            $lines[] = '    → ' . $d->recommendation;
            $lines[] = '';
        }

        $lines[] = sprintf('%d problema(s) encontrado(s).', count($diagnostics));

        return implode("\n", $lines) . "\n";
    }
}
