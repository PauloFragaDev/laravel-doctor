<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Tui;

use LaravelDoctor\Tui\EnvironmentState;
use LaravelDoctor\Tui\ProjectInfo;
use LaravelDoctor\Tui\TerminalApp;
use PhpTui\Term\KeyCode;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Verifica la LÓGICA de teclado del entorno (sin TTY): que '/' active búsqueda, que escribir
 * filtre y que Esc la cierre. El render/terminal real no se puede probar aquí.
 */
final class TerminalAppInputTest extends TestCase
{
    private function char(TerminalApp $app, EnvironmentState $s, string $c): bool
    {
        $m = new ReflectionMethod($app, 'handleChar');
        $m->setAccessible(true);

        return $m->invoke($app, $s, $c);
    }

    private function code(TerminalApp $app, EnvironmentState $s, KeyCode $code): void
    {
        $m = new ReflectionMethod($app, 'handleCoded');
        $m->setAccessible(true);
        $m->invoke($app, $s, $code);
    }

    private function searching(TerminalApp $app): bool
    {
        $p = new ReflectionProperty($app, 'searching');
        $p->setAccessible(true);

        return $p->getValue($app);
    }

    public function test_slash_enables_search_and_typing_filters(): void
    {
        $app = new TerminalApp();
        $state = new EnvironmentState([
            new ProjectInfo('blog', '/p/blog'),
            new ProjectInfo('shop', '/p/shop'),
        ]);

        $this->assertFalse($this->searching($app));

        $this->char($app, $state, 's');
        $this->assertTrue($this->searching($app), "'s' debe activar la búsqueda");
        // y '/' también
        $app2 = new TerminalApp();
        $this->char($app2, $state, '/');
        $this->assertTrue($this->searching($app2), "'/' debe activar la búsqueda");

        foreach (str_split('shop') as $c) {
            $this->char($app, $state, $c);
        }
        $this->assertSame('shop', $state->search);
        $this->assertSame(['shop'], array_map(fn ($p) => $p->name, $state->visibleProjects()));
    }

    public function test_escape_closes_search_keeping_query(): void
    {
        $app = new TerminalApp();
        $state = new EnvironmentState([new ProjectInfo('shop', '/p/shop')]);

        $this->char($app, $state, '/');
        $this->char($app, $state, 's');
        $this->code($app, $state, KeyCode::Esc);

        $this->assertFalse($this->searching($app), 'Esc debe cerrar el modo búsqueda');
        // Tras cerrar, las teclas vuelven a ser comandos: 'q' debe pedir salir.
        $this->assertTrue($this->char($app, $state, 'q'));
    }

    public function test_backspace_edits_query_while_searching(): void
    {
        $app = new TerminalApp();
        $state = new EnvironmentState([new ProjectInfo('shop', '/p/shop')]);

        $this->char($app, $state, '/');
        foreach (str_split('shx') as $c) {
            $this->char($app, $state, $c);
        }
        $this->code($app, $state, KeyCode::Backspace);
        $this->assertSame('sh', $state->search);
    }
}
