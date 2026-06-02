<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend;
use PhpTui\Tui\Display\Display;
use PhpTui\Tui\DisplayBuilder;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\List\ListItem;
use PhpTui\Tui\Extension\Core\Widget\ListWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;
use Throwable;

/**
 * Entorno full-screen real con php-tui: paneles, navegación por flechas, búsqueda en vivo y
 * detalle del hallazgo. Integración-only (necesita TTY); la lógica de navegación/filtro vive
 * en EnvironmentState (testeada).
 */
final class TerminalApp
{
    private Inspector $inspector;

    private bool $searching = false;

    private bool $boot = false;

    private string $currentPath = '';

    public function __construct(?Inspector $inspector = null)
    {
        $this->inspector = $inspector ?? new Inspector();
    }

    /**
     * @param ProjectInfo[] $projects
     */
    public function run(array $projects): int
    {
        $terminal = Terminal::new();
        $display = DisplayBuilder::default(PhpTermBackend::new($terminal))->build();
        $state = new EnvironmentState($projects);

        $terminal->execute(Actions::cursorHide());
        $terminal->execute(Actions::alternateScreenEnable());
        $terminal->enableRawMode();

        try {
            $this->loop($terminal, $display, $state);
        } finally {
            $terminal->disableRawMode();
            $terminal->execute(Actions::alternateScreenDisable());
            $terminal->execute(Actions::cursorShow());
            $terminal->execute(Actions::clear(ClearType::All));
        }

        return 0;
    }

    private function loop(Terminal $terminal, Display $display, EnvironmentState $state): void
    {
        while (true) {
            $display->draw($this->layout($state));

            while (null !== $event = $terminal->events()->next()) {
                if ($event instanceof CharKeyEvent && $event->modifiers === KeyModifiers::NONE) {
                    if ($this->handleChar($state, $event->char)) {
                        return;
                    }
                } elseif ($event instanceof CodedKeyEvent) {
                    $this->handleCoded($state, $event->code);
                }
            }

            usleep(20_000);
        }
    }

    private function handleChar(EnvironmentState $state, string $char): bool
    {
        if ($this->searching) {
            $state->appendSearch($char);

            return false;
        }
        if ($char === 'q') {
            return true; // salir
        }
        if ($char === '/') {
            $this->searching = true;
        }
        if ($char === 'b' && $state->screen === EnvironmentState::SCREEN_RESULTS) {
            // toggle runtime + re-analizar el proyecto cargado
            $this->boot = !$this->boot;
            $this->analyze($state, $state->loadedProjectName, $this->currentPath);
        }

        return false;
    }

    private function handleCoded(EnvironmentState $state, KeyCode $code): void
    {
        if ($this->searching) {
            match ($code) {
                KeyCode::Enter, KeyCode::Esc => $this->searching = false,
                KeyCode::Backspace => $state->backspaceSearch(),
                default => null,
            };

            return;
        }

        match ($code) {
            KeyCode::Up => $state->moveUp(),
            KeyCode::Down => $state->moveDown(),
            KeyCode::Enter => $this->onEnter($state),
            KeyCode::Esc => $state->screen === EnvironmentState::SCREEN_RESULTS ? $state->back() : null,
            default => null,
        };
    }

    private function onEnter(EnvironmentState $state): void
    {
        if ($state->screen !== EnvironmentState::SCREEN_HOME) {
            return;
        }
        $project = $state->selectedProject();
        if ($project !== null) {
            $this->boot = false;
            $this->analyze($state, $project->name, $project->path);
        }
    }

    private function analyze(EnvironmentState $state, string $name, string $path): void
    {
        $this->currentPath = $path;
        $result = $this->inspector->inspect($path, $this->boot);
        $state->openResults($name, $result->diagnostics, $result->score);
    }

    private function layout(EnvironmentState $state): Widget
    {
        return GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(Constraint::length(3), Constraint::min(1), Constraint::length(3))
            ->widgets($this->header($state), $this->body($state), $this->footer($state));
    }

    private function header(EnvironmentState $state): Widget
    {
        if ($state->screen === EnvironmentState::SCREEN_RESULTS && $state->score !== null) {
            $title = sprintf(
                ' 🩺 %s · Score %d/100 (%s)%s ',
                $state->loadedProjectName,
                $state->score->score,
                $state->score->label,
                $this->boot ? ' · runtime' : '',
            );
        } else {
            $title = ' 🩺 laravel-doctor · elige un proyecto ';
        }

        return BlockWidget::default()->borders(Borders::ALL)->titles(Title::fromString($title));
    }

    private function body(EnvironmentState $state): Widget
    {
        if ($state->screen === EnvironmentState::SCREEN_HOME) {
            return $this->projectsPane($state);
        }

        return GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(Constraint::percentage(55), Constraint::percentage(45))
            ->widgets($this->findingsPane($state), $this->detailPane($state));
    }

    private function projectsPane(EnvironmentState $state): Widget
    {
        $projects = $state->visibleProjects();
        $items = array_map(
            static fn (ProjectInfo $p) => ListItem::new(Text::fromString($p->name)),
            $projects,
        );

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString(sprintf(' Proyectos (%d) ', count($projects))))
            ->widget(
                ListWidget::default()
                    ->highlightSymbol('› ')
                    ->highlightStyle(Style::default()->cyan())
                    ->select($state->projectIndex)
                    ->items(...$items),
            );
    }

    private function findingsPane(EnvironmentState $state): Widget
    {
        $diagnostics = $state->visibleDiagnostics();
        $items = array_map(
            fn (Diagnostic $d) => ListItem::new(Text::parse(sprintf(
                '%s %s  <fg=gray>%s</>',
                $this->icon($d->severity),
                $d->ruleId,
                $this->relative($d->file),
            ))),
            $diagnostics,
        );

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString(sprintf(' Hallazgos (%d) ', count($diagnostics))))
            ->widget(
                ListWidget::default()
                    ->highlightSymbol('› ')
                    ->highlightStyle(Style::default()->cyan())
                    ->select($state->resultIndex)
                    ->items(...$items),
            );
    }

    private function detailPane(EnvironmentState $state): Widget
    {
        $d = $state->selectedDiagnostic();
        $lines = [];
        if ($d !== null) {
            $lines = [
                $this->icon($d->severity) . ' ' . $d->ruleId,
                '',
                $d->message,
                '',
                '<fg=green>→ ' . $d->recommendation . '</>',
                '',
                '<fg=gray>' . $this->relative($d->file) . ($d->line > 0 ? ':' . $d->line : '') . '</>',
            ];
        }
        $items = array_map(static fn (string $l) => ListItem::new(Text::parse($l)), $lines);

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString(' Detalle '))
            ->widget(ListWidget::default()->items(...$items));
    }

    private function footer(EnvironmentState $state): Widget
    {
        if ($this->searching) {
            $title = sprintf(' buscar: %s_   (Enter/Esc para salir) ', $state->search);
        } elseif ($state->screen === EnvironmentState::SCREEN_HOME) {
            $title = ' ↑↓ mover · Enter abrir · / buscar · q salir ';
        } else {
            $title = ' ↑↓ mover · / buscar · b runtime · Esc volver · q salir ';
        }

        return BlockWidget::default()->borders(Borders::ALL)->titles(Title::fromString($title));
    }

    private function relative(string $file): string
    {
        $base = rtrim($this->currentPath, '/') . '/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    private function icon(Severity $severity): string
    {
        return match ($severity) {
            Severity::Error => '<fg=red>✖</>',
            Severity::Warning => '<fg=yellow>⚠</>',
            Severity::Info => '<fg=blue>•</>',
        };
    }
}
