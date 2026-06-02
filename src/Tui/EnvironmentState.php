<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

/**
 * Estado PURO del entorno full-screen (php-tui): qué pantalla, índice seleccionado y la
 * búsqueda en vivo que filtra la lista activa. Sin E/S — testeable. TerminalApp traduce las
 * teclas a estas transiciones y construye los widgets a partir de este estado.
 */
final class EnvironmentState
{
    public const SCREEN_HOME = 'home';
    public const SCREEN_RESULTS = 'results';

    public string $screen = self::SCREEN_HOME;
    public int $projectIndex = 0;
    public int $resultIndex = 0;
    public string $search = '';
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

    /** @return Diagnostic[] */
    public function visibleDiagnostics(): array
    {
        $q = strtolower(trim($this->search));
        if ($q === '' || $this->screen !== self::SCREEN_RESULTS) {
            return $this->diagnostics;
        }

        return array_values(array_filter(
            $this->diagnostics,
            fn (Diagnostic $d) => str_contains(strtolower($d->ruleId . ' ' . $d->file . ' ' . $d->message), $q),
        ));
    }

    public function selectedProject(): ?ProjectInfo
    {
        return $this->visibleProjects()[$this->projectIndex] ?? null;
    }

    public function selectedDiagnostic(): ?Diagnostic
    {
        return $this->visibleDiagnostics()[$this->resultIndex] ?? null;
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
            $this->resultIndex = min(max(0, count($this->visibleDiagnostics()) - 1), $this->resultIndex + 1);
        }
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
}
