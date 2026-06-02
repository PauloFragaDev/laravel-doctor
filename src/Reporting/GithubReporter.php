<?php

declare(strict_types=1);

namespace LaravelDoctor\Reporting;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreResult;

/**
 * Emite los hallazgos como workflow commands de GitHub Actions, que se renderizan como
 * anotaciones inline en el PR/commit. Severidad error → ::error; warning/info → ::warning.
 */
final class GithubReporter
{
    /**
     * @param Diagnostic[] $diagnostics
     */
    public function report(ScoreResult $score, array $diagnostics): string
    {
        $lines = [];
        foreach ($diagnostics as $d) {
            $command = $d->severity === Severity::Error ? 'error' : 'warning';
            $lines[] = sprintf(
                '::%s file=%s,line=%d,title=%s::%s',
                $command,
                $d->file,
                $d->line,
                $d->ruleId,
                $this->escape($d->message . ' — ' . $d->recommendation),
            );
        }

        $lines[] = sprintf('::notice title=laravel-doctor::Score %d/100 (%s) — %d hallazgo(s)', $score->score, $score->label, count($diagnostics));

        return implode("\n", $lines) . "\n";
    }

    private function escape(string $value): string
    {
        // Escapado de datos para workflow commands de GitHub.
        return str_replace(["%", "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }
}
