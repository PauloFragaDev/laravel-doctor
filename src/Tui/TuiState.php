<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

/**
 * Máquina de estados PURA del TUI full-screen: navegación entre paneles, filtros y búsqueda.
 * Sin E/S de terminal — por eso es testeable. El bucle (FullScreenTui) traduce teclas a estas
 * transiciones y pinta el resultado de TuiRenderer.
 */
final class TuiState
{
    public const SCREEN_PROJECTS = 'projects';
    public const SCREEN_RESULTS = 'results';

    public const CATEGORIES = ['all', 'security', 'performance', 'eloquent', 'architecture'];

    public string $screen = self::SCREEN_PROJECTS;
    public int $projectIndex = 0;
    public int $resultIndex = 0;
    public string $category = 'all';
    public string $search = '';
    public bool $searching = false;
    public bool $expanded = false;
    public string $loadedProjectPath = '';
    public ?ScoreResult $score = null;

    /** @var Diagnostic[] */
    private array $diagnostics = [];

    /**
     * @param ProjectInfo[] $projects
     */
    public function __construct(public array $projects)
    {
    }

    public function currentProject(): ?ProjectInfo
    {
        return $this->projects[$this->projectIndex] ?? null;
    }

    /** @return Diagnostic[] */
    public function visibleDiagnostics(): array
    {
        $q = strtolower(trim($this->search));

        return array_values(array_filter($this->diagnostics, function (Diagnostic $d) use ($q) {
            if ($this->category !== 'all' && $d->category !== $this->category) {
                return false;
            }
            if ($q !== '' && !str_contains(strtolower($d->ruleId . ' ' . $d->file . ' ' . $d->message), $q)) {
                return false;
            }

            return true;
        }));
    }

    public function selectedDiagnostic(): ?Diagnostic
    {
        return $this->visibleDiagnostics()[$this->resultIndex] ?? null;
    }

    public function moveUp(): void
    {
        if ($this->screen === self::SCREEN_PROJECTS) {
            $this->projectIndex = max(0, $this->projectIndex - 1);
        } else {
            $this->resultIndex = max(0, $this->resultIndex - 1);
            $this->expanded = false;
        }
    }

    public function moveDown(): void
    {
        if ($this->screen === self::SCREEN_PROJECTS) {
            $this->projectIndex = min(max(0, count($this->projects) - 1), $this->projectIndex + 1);
        } else {
            $this->resultIndex = min(max(0, count($this->visibleDiagnostics()) - 1), $this->resultIndex + 1);
            $this->expanded = false;
        }
    }

    public function openProject(): void
    {
        $this->screen = self::SCREEN_RESULTS;
        $this->resultIndex = 0;
        $this->expanded = false;
    }

    /**
     * @param Diagnostic[] $diagnostics
     */
    public function setResults(string $projectPath, array $diagnostics, ScoreResult $score): void
    {
        $this->loadedProjectPath = $projectPath;
        $this->diagnostics = $diagnostics;
        $this->score = $score;
        $this->resultIndex = 0;
        $this->expanded = false;
    }

    public function back(): void
    {
        $this->screen = self::SCREEN_PROJECTS;
        $this->searching = false;
    }

    public function cycleCategory(): void
    {
        $i = array_search($this->category, self::CATEGORIES, true);
        $this->category = self::CATEGORIES[($i === false ? 0 : $i + 1) % count(self::CATEGORIES)];
        $this->resultIndex = 0;
        $this->expanded = false;
    }

    public function toggleExpand(): void
    {
        $this->expanded = !$this->expanded;
    }

    public function startSearch(): void
    {
        $this->searching = true;
    }

    public function endSearch(): void
    {
        $this->searching = false;
    }

    public function appendSearch(string $char): void
    {
        $this->search .= $char;
        $this->resultIndex = 0;
    }

    public function backspaceSearch(): void
    {
        $this->search = substr($this->search, 0, -1);
        $this->resultIndex = 0;
    }
}
