<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreResult;

/**
 * Pinta los resultados de una auditoría como bloque de texto con color (ANSI), agrupado por
 * categoría y con badges por severidad. Función pura → testeable (el texto/badges van en claro,
 * solo los códigos de color envuelven).
 */
final class ResultsRenderer
{
    private const RESET = "\e[0m";
    private const BOLD = "\e[1m";
    private const DIM = "\e[2m";

    private const CATEGORY_LABELS = [
        Categories::SECURITY => 'SEGURIDAD',
        Categories::PERFORMANCE => 'PERFORMANCE',
        Categories::ELOQUENT => 'ELOQUENT',
        Categories::ARCHITECTURE => 'ARQUITECTURA',
    ];

    private const ORDER = [
        Categories::SECURITY,
        Categories::PERFORMANCE,
        Categories::ELOQUENT,
        Categories::ARCHITECTURE,
    ];

    /**
     * @param Diagnostic[] $diagnostics
     */
    public function render(string $projectName, ScoreResult $score, array $diagnostics): string
    {
        $lines = ['', $this->header($projectName, $score, $diagnostics), ''];

        if ($diagnostics === []) {
            $lines[] = '  ' . $this->color('32', '✓ Sin hallazgos. 🎉');
            $lines[] = '';

            return implode("\n", $lines);
        }

        foreach (self::ORDER as $category) {
            $group = array_values(array_filter($diagnostics, fn (Diagnostic $d) => $d->category === $category));
            if ($group === []) {
                continue;
            }
            $lines[] = '  ' . self::BOLD . (self::CATEGORY_LABELS[$category] ?? strtoupper($category)) . self::RESET;
            foreach ($group as $d) {
                $loc = $d->line > 0 ? $d->file . ':' . $d->line : $d->file;
                $lines[] = sprintf(
                    '   %s %s   %s',
                    $this->icon($d->severity),
                    str_pad($d->ruleId, 32),
                    self::DIM . $loc . self::RESET,
                );
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function header(string $projectName, ScoreResult $score, array $diagnostics): string
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($diagnostics as $d) {
            $counts[$d->severity->value]++;
        }

        $scoreColor = $score->score >= 90 ? '32' : ($score->score >= 70 ? '33' : '31');

        return sprintf(
            '  %s%s%s  %s·%s  Score %s%d/100%s (%s)  %s·%s  %s %s %s',
            self::BOLD,
            $projectName,
            self::RESET,
            self::DIM,
            self::RESET,
            $this->color($scoreColor, ''),
            $score->score,
            self::RESET,
            $score->label,
            self::DIM,
            self::RESET,
            $this->color('31', '✖' . $counts['error']),
            $this->color('33', '⚠' . $counts['warning']),
            $this->color('34', '•' . $counts['info']),
        );
    }

    private function icon(Severity $severity): string
    {
        return match ($severity) {
            Severity::Error => $this->color('31', '✖'),
            Severity::Warning => $this->color('33', '⚠'),
            Severity::Info => $this->color('34', '•'),
        };
    }

    private function color(string $code, string $text): string
    {
        return "\e[" . $code . 'm' . $text . self::RESET;
    }
}
