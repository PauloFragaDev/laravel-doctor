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
use PhpTui\Tui\Canvas\CanvasContext;
use PhpTui\Tui\Canvas\Marker;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Display\Display;
use PhpTui\Tui\DisplayBuilder;
use PhpTui\Tui\Extension\Core\Shape\LineShape;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\CanvasWidget;
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
            $width = max(20, $display->viewportArea()->width);
            $display->draw($this->layout($state, $tick++, $width));

            while (null !== $event = $terminal->events()->next()) {
                if ($event instanceof CharKeyEvent && $event->modifiers === KeyModifiers::NONE) {
                    if ($this->handleChar($state, $event->char)) {
                        return;
                    }
                } elseif ($event instanceof CodedKeyEvent) {
                    $this->handleCoded($state, $event->code);
                }
            }

            usleep(40_000);
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

    private function layout(EnvironmentState $state, int $tick, int $width): Widget
    {
        $grid = GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(Constraint::length(7), Constraint::length(3), Constraint::min(1), Constraint::length(3))
            ->widgets($this->header($state, $tick, $width), $this->bar($state), $this->body($state, $tick), $this->footer($state));

        // Bloque raíz sin bordes con fondo negro: fuerza el negro en toda la pantalla.
        return BlockWidget::default()
            ->style(Style::default()->onBlack())
            ->widget($grid);
    }

    private function header(EnvironmentState $state, int $tick, int $width): Widget
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

        // Título a la izquierda, ECG (canvas braille) a la derecha.
        $rows = 5;
        $titleLines = array_fill(0, $rows, '');
        $titleLines[intdiv($rows, 2)] = ' <options=bold>' . $label . '</>';

        return $this->block()->widget(
            GridWidget::default()
                ->direction(Direction::Horizontal)
                ->constraints(Constraint::length(44), Constraint::min(1))
                ->widgets(
                    ParagraphWidget::fromText(Text::parse(implode("\n", $titleLines))),
                    $this->ecgCanvas($tick),
                ),
        );
    }

    /**
     * Electrocardiograma animado (tema "doctor") dibujado en un canvas con marcador braille
     * (alta resolución → línea suave, como btop). La traza se desplaza con el tick.
     */
    private function ecgCanvas(int $tick): Widget
    {
        $scroll = $tick * 2.0;

        return CanvasWidget::fromIntBounds(0, 100, 0, 100)
            ->marker(Marker::Braille)
            ->paint(function (CanvasContext $ctx) use ($scroll): void {
                $prevX = 0.0;
                $prevY = $this->ecgY($scroll);
                for ($x = 1; $x <= 100; $x++) {
                    $y = $this->ecgY($x + $scroll);
                    $ctx->draw(
                        LineShape::fromScalars($prevX, $prevY, (float) $x, $y)->color(AnsiColor::Cyan),
                    );
                    $prevX = (float) $x;
                    $prevY = $y;
                }
            });
    }

    /** Altura (0..100) de la traza ECG para una posición; un latido se repite cada L unidades. */
    private function ecgY(float $globalX): float
    {
        $beatLength = 26.0;
        $base = 42.0;
        $p = fmod(fmod($globalX, $beatLength) + $beatLength, $beatLength) / $beatLength;

        // Puntos de control de un latido (fracción de fase => desviación sobre la baseline).
        $cp = [
            [0.00, 0.0], [0.30, 0.0],
            [0.345, 12.0], [0.39, 0.0],   // onda P
            [0.45, 0.0],
            [0.475, -12.0],               // Q
            [0.50, 48.0],                 // R (pico)
            [0.525, -20.0],               // S
            [0.55, 0.0], [0.66, 0.0],
            [0.72, 18.0], [0.80, 0.0],    // onda T
            [1.00, 0.0],
        ];

        for ($i = 1, $n = count($cp); $i < $n; $i++) {
            if ($p <= $cp[$i][0]) {
                $span = max(1e-6, $cp[$i][0] - $cp[$i - 1][0]);
                $t = ($p - $cp[$i - 1][0]) / $span;

                return $base + $cp[$i - 1][1] + ($cp[$i][1] - $cp[$i - 1][1]) * $t;
            }
        }

        return $base;
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

    private function body(EnvironmentState $state, int $tick): Widget
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
