<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Analysis\Inspector;

/**
 * Bucle interactivo de pantalla completa. Pone la terminal en modo raw, lee teclas y pinta lo
 * que produce TuiRenderer según las transiciones de TuiState. Integración-only (necesita un TTY
 * de verdad): la lógica vive en TuiState/TuiRenderer, que sí están testeados.
 */
final class FullScreenTui
{
    private Inspector $inspector;

    private ProjectDiscovery $discovery;

    private TuiRenderer $renderer;

    private bool $boot = false;

    public function __construct(?Inspector $inspector = null, ?ProjectDiscovery $discovery = null)
    {
        $this->inspector = $inspector ?? new Inspector();
        $this->discovery = $discovery ?? new ProjectDiscovery();
        $this->renderer = new TuiRenderer();
    }

    public function run(string $base): int
    {
        $projects = $this->discovery->discover($base);
        if ($projects === []) {
            fwrite(STDOUT, sprintf("No se encontraron proyectos Laravel en %s.\n", $base));

            return 0;
        }

        $state = new TuiState($projects);
        $stty = trim((string) @shell_exec('stty -g 2>/dev/null'));

        @shell_exec('stty -echo -icanon min 1 time 0 2>/dev/null');
        fwrite(STDOUT, "\e[?1049h\e[?25l"); // pantalla alternativa + ocultar cursor

        try {
            $this->loop($state);
        } finally {
            fwrite(STDOUT, "\e[?25h\e[?1049l"); // mostrar cursor + restaurar pantalla
            if ($stty !== '') {
                @shell_exec('stty ' . escapeshellarg($stty) . ' 2>/dev/null');
            }
        }

        return 0;
    }

    private function loop(TuiState $state): void
    {
        $width = (int) (trim((string) @shell_exec('tput cols 2>/dev/null')) ?: 100);

        while (true) {
            fwrite(STDOUT, "\e[H\e[2J" . $this->renderer->render($state, $width));

            $key = fread(STDIN, 16);
            if ($key === false || $key === '') {
                continue;
            }

            if ($state->searching) {
                $this->handleSearchKey($state, $key);
                continue;
            }

            if ($key === 'q') {
                return;
            }
            if ($key === "\e[A") { $state->moveUp(); continue; }
            if ($key === "\e[B") { $state->moveDown(); continue; }

            if ($state->screen === TuiState::SCREEN_PROJECTS) {
                if ($key === "\n" || $key === "\r") {
                    $this->openSelected($state);
                }
                continue;
            }

            // Pantalla de resultados
            match (true) {
                $key === "\n" || $key === "\r" => $state->toggleExpand(),
                $key === "\e" || $key === "\e[D" => $state->back(),
                $key === 'c' => $state->cycleCategory(),
                $key === '/' => $state->startSearch(),
                $key === 'b' => $this->toggleBoot($state),
                default => null,
            };
        }
    }

    private function handleSearchKey(TuiState $state, string $key): void
    {
        if ($key === "\n" || $key === "\r" || $key === "\e") {
            $state->endSearch();

            return;
        }
        if ($key === "\x7f") {
            $state->backspaceSearch();

            return;
        }
        if (strlen($key) === 1 && ctype_print($key)) {
            $state->appendSearch($key);
        }
    }

    private function openSelected(TuiState $state): void
    {
        $project = $state->currentProject();
        if ($project === null) {
            return;
        }
        $state->openProject();
        $result = $this->inspector->inspect($project->path, $this->boot);
        $state->setResults($project->path, $result->diagnostics, $result->score);
    }

    private function toggleBoot(TuiState $state): void
    {
        $this->boot = !$this->boot;
        $project = $state->currentProject();
        if ($project !== null) {
            $result = $this->inspector->inspect($project->path, $this->boot);
            $state->setResults($project->path, $result->diagnostics, $result->score);
        }
    }
}
