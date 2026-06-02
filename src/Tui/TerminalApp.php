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
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

/**
 * Entorno full-screen real con php-tui: cabecera con score, barra de tabs de categoría +
 * buscador, hallazgos en bloques por categoría, panel de detalle y footer de atajos. La lógica
 * (navegación/filtro/agrupado) vive en EnvironmentState (testeada); esto es el bucle de E/S.
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
        $display = DisplayBuilder::default(PhpTermBackend::new($terminal))->fullscreen()->build();
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
        $tick = 0;
        while (true) {
            $display->draw($this->layout($state, $tick++));

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
            return true;
        }
        if ($char === '/') {
            $this->searching = true;
        } elseif ($char === 'c' && $state->screen === EnvironmentState::SCREEN_RESULTS) {
            $state->cycleCategory();
        } elseif ($char === 'b' && $state->screen === EnvironmentState::SCREEN_RESULTS) {
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
            KeyCode::Right, KeyCode::Tab => $state->screen === EnvironmentState::SCREEN_RESULTS ? $state->cycleCategory() : null,
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

    private function layout(EnvironmentState $state, int $tick): Widget
    {
        $grid = GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(Constraint::length(3), Constraint::length(3), Constraint::min(1), Constraint::length(3))
            ->widgets($this->header($state, $tick), $this->bar($state), $this->body($state), $this->footer($state));

        // Bloque raíz sin bordes con fondo negro: fuerza el negro en toda la pantalla.
        return BlockWidget::default()
            ->style(Style::default()->onBlack())
            ->widget($grid);
    }

    private function header(EnvironmentState $state, int $tick): Widget
    {
        if ($state->screen === EnvironmentState::SCREEN_RESULTS && $state->score !== null) {
            $label = sprintf(
                '🩺 %s · Score %d/100 (%s)%s',
                $state->loadedProjectName,
                $state->score->score,
                $state->score->label,
                $this->boot ? ' · runtime' : '',
            );
        } else {
            $label = '🩺 laravel-doctor · elige un proyecto';
        }

        return $this->block()->widget(
            ParagraphWidget::fromText(Text::parse(' ' . $label . '   ' . $this->logo($tick) . ' ')),
        );
    }

    /** Logo animado: mini-dots con una onda de brillo (fade) que va y viene. */
    private function logo(int $tick): string
    {
        $n = 14;
        $t = intdiv($tick, 3);
        $pos = abs(($t % (2 * ($n - 1))) - ($n - 1)); // ping-pong 0..n-1..0
        $out = '';
        for ($i = 0; $i < $n; $i++) {
            $d = abs($i - $pos);
            $out .= match (true) {
                $d === 0 => '<options=bold;fg=white>●</>',
                $d === 1 => '<fg=cyan>●</>',
                $d === 2 => '<fg=gray>•</>',
                default => '<fg=darkgray>·</>',
            };
        }

        return $out;
    }

    /** Bloque base: bordes y fondo negro (personalización). */
    private function block(): BlockWidget
    {
        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->style(Style::default()->onBlack());
    }

    private function bar(EnvironmentState $state): Widget
    {
        $search = sprintf('🔎 %s%s', $state->search, $this->searching ? '_' : '');

        if ($state->screen === EnvironmentState::SCREEN_RESULTS) {
            $tabs = [];
            foreach (EnvironmentState::CATEGORIES as $key => $label) {
                $tabs[] = $key === $state->category
                    ? '<options=bold;fg=cyan>[' . $label . ']</>'
                    : '<fg=gray>' . $label . '</>';
            }
            $content = ' ' . implode('  ', $tabs) . '     ' . $search . ' ';
        } else {
            $content = ' ' . $search . ' ';
        }

        return $this->block()->widget(ParagraphWidget::fromText(Text::parse($content)));
    }

    private function body(EnvironmentState $state): Widget
    {
        if ($state->screen === EnvironmentState::SCREEN_HOME) {
            return $this->projectsPane($state);
        }

        return GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(Constraint::percentage(60), Constraint::percentage(40))
            ->widgets($this->findingsPane($state), $this->detailPane($state));
    }

    private function projectsPane(EnvironmentState $state): Widget
    {
        $projects = $state->visibleProjects();
        $items = array_map(
            static fn (ProjectInfo $p) => ListItem::new(Text::fromString($p->name)),
            $projects,
        );

        return $this->block()
            ->titles(Title::fromString(sprintf(' Proyectos (%d) ', count($projects))))
            ->widget(
                ListWidget::default()
                    ->highlightSymbol('› ')
                    ->highlightStyle(Style::default()->cyan())
                    ->select($projects === [] ? null : $state->projectIndex)
                    ->items(...$items),
            );
    }

    private function findingsPane(EnvironmentState $state): Widget
    {
        $items = [];
        foreach ($state->groupedRows() as $row) {
            if ($row['kind'] === 'header') {
                $items[] = ListItem::new(Text::parse(sprintf(
                    '<options=bold>%s</> <fg=gray>(%d)</>',
                    strtoupper((string) ($row['label'] ?? '')),
                    (int) ($row['count'] ?? 0),
                )));
                continue;
            }
            /** @var Diagnostic $d */
            $d = $row['diagnostic'];
            $items[] = ListItem::new(Text::parse(sprintf(
                '  %s %s  <fg=gray>%s</>',
                $this->icon($d->severity),
                $d->ruleId,
                $this->relative($d->file) . ($d->line > 0 ? ':' . $d->line : ''),
            )));
        }

        $count = count($state->visibleFindings());

        return $this->block()
            ->titles(Title::fromString(sprintf(' Hallazgos (%d) ', $count)))
            ->widget(
                ListWidget::default()
                    ->highlightSymbol('› ')
                    ->highlightStyle(Style::default()->cyan())
                    ->select($count > 0 ? $state->selectedRowIndex() : null)
                    ->items(...$items),
            );
    }

    private function detailPane(EnvironmentState $state): Widget
    {
        $d = $state->selectedDiagnostic();
        $text = $d === null
            ? '<fg=gray>Sin hallazgos.</>'
            : sprintf(
                "%s <options=bold>%s</>\n\n%s\n\n<fg=green>→ %s</>\n\n<fg=gray>%s</>",
                $this->icon($d->severity),
                $d->ruleId,
                $d->message,
                $d->recommendation,
                $this->relative($d->file) . ($d->line > 0 ? ':' . $d->line : ''),
            );

        return $this->block()
            ->titles(Title::fromString(' Detalle '))
            ->widget(ParagraphWidget::fromText(Text::parse($text)));
    }

    private function footer(EnvironmentState $state): Widget
    {
        if ($this->searching) {
            $title = ' escribe para filtrar · Enter/Esc para salir de la búsqueda ';
        } elseif ($state->screen === EnvironmentState::SCREEN_HOME) {
            $title = ' ↑↓ mover · Enter abrir · / buscar · q salir ';
        } else {
            $title = ' ↑↓ mover · Tab/c categoría · / buscar · b runtime · Esc volver · q salir ';
        }

        return $this->block()->titles(Title::fromString($title));
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
