<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;

use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * Experiencia interactiva moderna basada en laravel/prompts: selección de proyecto con scroll,
 * spinner al analizar, resultados a color (ResultsRenderer) y menús para filtrar, ver detalle y
 * alternar --boot. Integración-only (necesita TTY); la presentación vive en ResultsRenderer.
 */
final class InteractiveSession
{
    private Inspector $inspector;

    private ResultsRenderer $renderer;

    private const CATEGORY_OPTIONS = [
        'all' => 'Todas',
        Categories::SECURITY => 'Seguridad',
        Categories::PERFORMANCE => 'Performance',
        Categories::ELOQUENT => 'Eloquent',
        Categories::ARCHITECTURE => 'Arquitectura',
    ];

    public function __construct(?Inspector $inspector = null)
    {
        $this->inspector = $inspector ?? new Inspector();
        $this->renderer = new ResultsRenderer();
    }

    /**
     * @param ProjectInfo[] $projects
     */
    public function run(array $projects): int
    {
        $byName = [];
        foreach ($projects as $project) {
            $byName[$project->name] = $project->path;
        }

        while (true) {
            $choice = select(
                label: 'Proyecto a auditar',
                options: [...array_keys($byName), '⏻ Salir'],
                scroll: 12,
            );
            if ($choice === '⏻ Salir') {
                return 0;
            }
            if ($this->project($choice, $byName[$choice])) {
                return 0;
            }
        }
    }

    private function project(string $name, string $path): bool
    {
        $boot = false;
        $category = null;

        while (true) {
            $result = spin(
                fn () => $this->inspector->inspect($path, $boot),
                sprintf('Analizando %s%s…', $name, $boot ? ' (runtime)' : ''),
            );

            $diagnostics = $this->filter($result->diagnostics, $category);
            echo $this->renderer->render($name, $path, $result->score, $diagnostics);

            $action = select('¿Qué quieres hacer?', [
                'detail' => 'Ver detalle de un hallazgo',
                'filter' => 'Filtrar por categoría' . ($category ? ' [' . self::CATEGORY_OPTIONS[$category] . ']' : ''),
                'boot' => ($boot ? 'Desactivar' : 'Activar') . ' análisis runtime (--boot)',
                'change' => 'Cambiar de proyecto',
                'exit' => 'Salir',
            ]);

            switch ($action) {
                case 'detail':
                    $this->detail($diagnostics, $path);
                    break;
                case 'filter':
                    $picked = select('Categoría', self::CATEGORY_OPTIONS, default: $category ?? 'all');
                    $category = $picked === 'all' ? null : $picked;
                    break;
                case 'boot':
                    $boot = !$boot;
                    break;
                case 'change':
                    return false;
                case 'exit':
                    return true;
            }
        }
    }

    /**
     * @param Diagnostic[] $diagnostics
     * @return Diagnostic[]
     */
    private function filter(array $diagnostics, ?string $category): array
    {
        if ($category === null) {
            return $diagnostics;
        }

        return array_values(array_filter($diagnostics, fn (Diagnostic $d) => $d->category === $category));
    }

    /**
     * @param Diagnostic[] $diagnostics
     */
    private function detail(array $diagnostics, string $path): void
    {
        if ($diagnostics === []) {
            note('No hay hallazgos que mostrar.');

            return;
        }

        $options = [];
        foreach ($diagnostics as $i => $d) {
            $options[(string) $i] = sprintf('%s  %s', $d->ruleId, $this->renderer->location($d, $path));
        }
        $options['back'] = '← Volver';

        $choice = select('¿Qué hallazgo? (↑↓ y Enter)', $options, scroll: 15);
        if ($choice === 'back') {
            return;
        }
        $d = $diagnostics[(int) $choice];

        $body = $d->ruleId . "\n\n" . $d->message . "\n\n→ " . $d->recommendation;
        $snippet = $this->snippet($d->file, $d->line, $path);
        if ($snippet !== null) {
            $body .= "\n\n" . $d->file . ':' . $d->line . "\n  " . $snippet;
        }

        note($body);
    }

    private function snippet(string $file, int $line, string $projectPath): ?string
    {
        if ($line < 1) {
            return null;
        }
        $real = realpath($file);
        $base = realpath($projectPath);
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            return null;
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        return ($lines === false || !isset($lines[$line - 1])) ? null : trim($lines[$line - 1]);
    }
}
