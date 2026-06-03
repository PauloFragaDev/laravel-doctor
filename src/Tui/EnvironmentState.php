<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

/**
 * Estado PURO del entorno full-screen (php-tui): pantalla, selección, filtro por categoría y
 * búsqueda en vivo, más el agrupado en bloques por categoría. Sin E/S — testeable. TerminalApp
 * traduce teclas a estas transiciones y construye los widgets a partir de este estado.
 */
final class EnvironmentState
{
    public const SCREEN_HOME = 'home';
    public const SCREEN_RESULTS = 'results';

    /** Categorías para el tab-bar (la primera, 'all', muestra todo en bloques). */
    public const CATEGORIES = [
        'all' => 'Todas',
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

    public string $screen = self::SCREEN_HOME;
    public int $projectIndex = 0;
    public int $resultIndex = 0;
    public string $search = '';
    public string $category = 'all';
    public string $loadedProjectName = '';
    public ?ScoreResult $score = null;

    /** @var Diagnostic[] */
    private array $diagnostics = [];

    /**
     * @param ProjectInfo[] $projects
     */
    public function __construct(public array $projects)
    {
    }

    /** @return ProjectInfo[] */
    public function visibleProjects(): array
    {
        $q = strtolower(trim($this->search));
        if ($q === '' || $this->screen !== self::SCREEN_HOME) {
            return $this->projects;
        }

        return array_values(array_filter($this->projects, fn (ProjectInfo $p) => str_contains(strtolower($p->name), $q)));
    }

    public function selectedProject(): ?ProjectInfo
    {
        return $this->visibleProjects()[$this->projectIndex] ?? null;
    }

    /**
     * Hallazgos visibles (tras filtro de categoría y búsqueda), en orden de bloque por categoría.
     *
     * @return Diagnostic[]
     */
    public function visibleFindings(): array
    {
        $findings = [];
        foreach ($this->grouped() as $group) {
            foreach ($group as $d) {
                $findings[] = $d;
            }
        }

        return $findings;
    }

    /**
     * Filas para pintar: cabeceras de bloque (kind=header) intercaladas con hallazgos
     * (kind=finding). Con filtro de categoría, solo ese bloque.
     *
     * @return list<array{kind:string,category?:string,label?:string,count?:int,diagnostic?:Diagnostic}>
     */
    public function groupedRows(): array
    {
        $rows = [];
        foreach ($this->grouped() as $category => $group) {
            $rows[] = [
                'kind' => 'header',
                'category' => $category,
                'label' => self::CATEGORIES[$category] ?? ucfirst($category),
                'count' => count($group),
            ];
            foreach ($group as $d) {
                $rows[] = ['kind' => 'finding', 'diagnostic' => $d];
            }
        }

        return $rows;
    }

    /** Índice (en groupedRows) de la fila del hallazgo seleccionado, para resaltarlo. */
    public function selectedRowIndex(): int
    {
        $selected = $this->selectedDiagnostic();
        if ($selected === null) {
            return 0;
        }
        $i = 0;
        foreach ($this->groupedRows() as $row) {
            if ($row['kind'] === 'finding' && ($row['diagnostic'] ?? null) === $selected) {
                return $i;
            }
            $i++;
        }

        return 0;
    }

    public function selectedDiagnostic(): ?Diagnostic
    {
        return $this->visibleFindings()[$this->resultIndex] ?? null;
    }

    public function moveUp(): void
    {
        if ($this->screen === self::SCREEN_HOME) {
            $this->projectIndex = max(0, $this->projectIndex - 1);
        } else {
            $this->resultIndex = max(0, $this->resultIndex - 1);
        }
    }

    public function moveDown(): void
    {
        if ($this->screen === self::SCREEN_HOME) {
            $this->projectIndex = min(max(0, count($this->visibleProjects()) - 1), $this->projectIndex + 1);
        } else {
            $this->resultIndex = min(max(0, count($this->visibleFindings()) - 1), $this->resultIndex + 1);
        }
    }

    public function cycleCategory(): void
    {
        $keys = array_keys(self::CATEGORIES);
        $i = array_search($this->category, $keys, true);
        $this->category = $keys[($i === false ? 0 : $i + 1) % count($keys)];
        $this->resultIndex = 0;
    }

    /**
     * @param Diagnostic[] $diagnostics
     */
    public function openResults(string $projectName, array $diagnostics, ScoreResult $score): void
    {
        $this->screen = self::SCREEN_RESULTS;
        $this->loadedProjectName = $projectName;
        $this->diagnostics = $diagnostics;
        $this->score = $score;
        $this->resultIndex = 0;
        $this->search = '';
        $this->category = 'all';
    }

    public function back(): void
    {
        $this->screen = self::SCREEN_HOME;
        $this->search = '';
        $this->resultIndex = 0;
    }

    public function appendSearch(string $char): void
    {
        $this->search .= $char;
        $this->projectIndex = 0;
        $this->resultIndex = 0;
    }

    public function backspaceSearch(): void
    {
        $this->search = substr($this->search, 0, -1);
        $this->projectIndex = 0;
        $this->resultIndex = 0;
    }

    /**
     * Hallazgos agrupados por categoría (en orden), aplicando filtro de categoría y búsqueda.
     *
     * @return array<string,Diagnostic[]>
     */
    private function grouped(): array
    {
        $q = strtolower(trim($this->search));
        $grouped = [];
        foreach (self::ORDER as $category) {
            if ($this->category !== 'all' && $this->category !== $category) {
                continue;
            }
            $group = array_values(array_filter($this->diagnostics, function (Diagnostic $d) use ($category, $q) {
                if ($d->category !== $category) {
                    return false;
                }

                return $q === '' || str_contains(strtolower($d->ruleId . ' ' . $d->file . ' ' . $d->message), $q);
            }));
            if ($group !== []) {
                $grouped[$category] = $group;
            }
        }

        return $grouped;
    }
}
