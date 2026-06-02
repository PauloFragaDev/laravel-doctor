<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;

/**
 * Pinta el estado del TUI como una cadena (pantalla completa). Función pura: estado → texto,
 * por eso es testeable. El bucle (FullScreenTui) solo limpia la pantalla e imprime esto.
 */
final class TuiRenderer
{
    private const LEFT_WIDTH = 24;

    public function render(TuiState $state, int $width = 100): string
    {
        $lines = [];
        $lines[] = $this->header($state, $width);
        $lines[] = str_repeat('─', $width);

        $rightWidth = max(20, $width - self::LEFT_WIDTH - 3);
        $projects = $state->projects;
        $diagnostics = $state->visibleDiagnostics();
        $rows = max(count($projects), count($diagnostics), 1);

        for ($i = 0; $i < $rows; $i++) {
            $left = $this->projectCell($state, $i);
            $right = $this->diagnosticCell($state, $diagnostics[$i] ?? null, $i);
            $lines[] = $this->pad($left, self::LEFT_WIDTH) . ' │ ' . $this->truncate($right, $rightWidth);
        }

        if ($state->screen === TuiState::SCREEN_RESULTS && $state->expanded && $state->selectedDiagnostic() !== null) {
            $lines[] = str_repeat('─', $width);
            foreach ($this->detail($state->selectedDiagnostic()) as $detailLine) {
                $lines[] = $detailLine;
            }
        }

        $lines[] = str_repeat('─', $width);
        $lines[] = $this->footer($state);

        return implode("\n", $lines) . "\n";
    }

    private function header(TuiState $state, int $width): string
    {
        $project = $state->currentProject()?->name ?? '—';
        if ($state->score === null) {
            return sprintf('laravel-doctor · %s', $project);
        }

        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($state->visibleDiagnostics() as $d) {
            $counts[$d->severity->value]++;
        }

        return sprintf(
            'laravel-doctor · %s · Score %d/100 (%s) · ●%d ●%d ●%d',
            $project,
            $state->score->score,
            $state->score->label,
            $counts['error'],
            $counts['warning'],
            $counts['info'],
        );
    }

    private function projectCell(TuiState $state, int $i): string
    {
        if (!isset($state->projects[$i])) {
            return '';
        }
        $active = $state->screen === TuiState::SCREEN_PROJECTS && $state->projectIndex === $i;

        return ($active ? '› ' : '  ') . $state->projects[$i]->name;
    }

    private function diagnosticCell(TuiState $state, ?Diagnostic $d, int $i): string
    {
        if ($d === null) {
            return '';
        }
        $active = $state->screen === TuiState::SCREEN_RESULTS && $state->resultIndex === $i;
        $loc = $d->line > 0 ? sprintf('%s:%d', $d->file, $d->line) : $d->file;

        return ($active ? '› ' : '  ') . sprintf('%-7s %s  %s', strtoupper($d->severity->value), $d->ruleId, $loc);
    }

    /**
     * @return string[]
     */
    private function detail(Diagnostic $d): array
    {
        return [
            '  ' . $d->message,
            '  → ' . $d->recommendation,
        ];
    }

    private function footer(TuiState $state): string
    {
        if ($state->searching) {
            return 'buscar: ' . $state->search . '▏   (Enter/Esc para salir de la búsqueda)';
        }
        if ($state->screen === TuiState::SCREEN_PROJECTS) {
            return '↑↓ mover · Enter abrir · q salir';
        }

        return sprintf(
            '↑↓ mover · Enter detalle · c categoría [%s] · / buscar · Esc volver · q salir',
            $state->category,
        );
    }

    private function pad(string $text, int $width): string
    {
        $text = $this->truncate($text, $width);

        return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    private function truncate(string $text, int $width): string
    {
        return mb_strlen($text) > $width ? mb_substr($text, 0, max(0, $width - 1)) . '…' : $text;
    }
}
