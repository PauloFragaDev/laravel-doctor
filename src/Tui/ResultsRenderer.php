<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreResult;

/**
 * Pinta los resultados de una auditoría como bloque de texto con color (ANSI), agrupado por
 * categoría y con conteos por severidad. Función pura → testeable (texto en claro, los códigos
 * de color solo envuelven). Las rutas se muestran relativas al proyecto.
 */
final class ResultsRenderer
{
    private const RESET = "\e[0m";
    private const BOLD = "\e[1m";
    private const DIM = "\e[2m";

    private const CATEGORY_LABELS = [
        Categories::SECURITY => 'Seguridad',
        Categories::PERFORMANCE => 'Performance',
        Categories::ELOQUENT => 'Eloquent',
        Categories::ARCHITECTURE => 'Arquitectura',
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
    public function render(string $projectName, string $projectPath, ScoreResult $score, array $diagnostics): string
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($diagnostics as $d) {
            $counts[$d->severity->value]++;
        }

        $scoreColor = $score->score >= 90 ? '32' : ($score->score >= 70 ? '33' : '31');

        $lines = [
            '',
            '  ' . self::BOLD . $projectName . self::RESET . '   '
                . $this->color($scoreColor, sprintf('Score %d/100', $score->score))
                . self::DIM . ' · ' . $score->label . self::RESET,
            '  ' . $this->color('31', '✖ ' . $counts['error'] . ' errores') . '    '
                . $this->color('33', '⚠ ' . $counts['warning'] . ' avisos') . '    '
                . $this->color('34', '• ' . $counts['info'] . ' info'),
            '',
        ];

        if ($diagnostics === []) {
            $lines[] = '  ' . $this->color('32', '✓ Sin hallazgos. 🎉');
            $lines[] = '';

            return implode("\n", $lines);
        }

        // Ancho de la columna de la regla para alinear la ubicación.
        $ruleWidth = 0;
        foreach ($diagnostics as $d) {
            $ruleWidth = max($ruleWidth, mb_strlen($d->ruleId));
        }

        foreach (self::ORDER as $category) {
            $group = array_values(array_filter($diagnostics, fn (Diagnostic $d) => $d->category === $category));
            if ($group === []) {
                continue;
            }
            $label = self::CATEGORY_LABELS[$category] ?? ucfirst($category);
            $lines[] = '  ' . self::BOLD . strtoupper($label) . self::RESET
                . self::DIM . ' (' . count($group) . ')' . self::RESET;
            foreach ($group as $d) {
                $lines[] = sprintf(
                    '    %s  %s  %s',
                    $this->icon($d->severity),
                    str_pad($d->ruleId, $ruleWidth),
                    self::DIM . $this->location($d, $projectPath) . self::RESET,
                );
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function location(Diagnostic $d, string $projectPath): string
    {
        $file = $this->relative($d->file, $projectPath);

        return $d->line > 0 ? $file . ':' . $d->line : $file;
    }

    private function relative(string $file, string $projectPath): string
    {
        $base = rtrim($projectPath, '/') . '/';
        if (str_starts_with($file, $base)) {
            return substr($file, strlen($base));
        }

        return $file;
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
