<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Tui;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreResult;
use LaravelDoctor\Tui\EnvironmentState;
use LaravelDoctor\Tui\ProjectInfo;
use PHPUnit\Framework\TestCase;

final class EnvironmentStateTest extends TestCase
{
    private function state(): EnvironmentState
    {
        return new EnvironmentState([
            new ProjectInfo('blog', '/p/blog'),
            new ProjectInfo('shop', '/p/shop'),
            new ProjectInfo('shopify-bridge', '/p/sb'),
        ]);
    }

    private function d(string $rule): Diagnostic
    {
        return new Diagnostic($rule, Categories::SECURITY, Severity::Error, 'app/X.php', 1, 'm', 'r');
    }

    public function test_live_search_filters_projects(): void
    {
        $s = $this->state();
        foreach (str_split('shop') as $c) {
            $s->appendSearch($c);
        }
        $names = array_map(fn ($p) => $p->name, $s->visibleProjects());
        $this->assertSame(['shop', 'shopify-bridge'], $names);
        $this->assertSame('shop', $s->selectedProject()->name);
    }

    public function test_navigation_clamped_to_visible(): void
    {
        $s = $this->state();
        $s->moveDown();
        $s->moveDown();
        $s->moveDown(); // más allá del final (3 proyectos)
        $this->assertSame('shopify-bridge', $s->selectedProject()->name);
    }

    public function test_open_results_and_back(): void
    {
        $s = $this->state();
        $s->openResults('shop', [$this->d('a'), $this->d('b')], new ScoreResult(80, 'Needs work'));
        $this->assertSame(EnvironmentState::SCREEN_RESULTS, $s->screen);
        $this->assertCount(2, $s->visibleDiagnostics());

        $s->back();
        $this->assertSame(EnvironmentState::SCREEN_HOME, $s->screen);
        $this->assertSame('', $s->search);
    }

    public function test_search_filters_diagnostics_in_results(): void
    {
        $s = $this->state();
        $s->openResults('shop', [$this->d('no-env-outside-config'), $this->d('no-query-in-loop')], new ScoreResult(80, 'Needs work'));
        foreach (str_split('query') as $c) {
            $s->appendSearch($c);
        }
        $this->assertCount(1, $s->visibleDiagnostics());
        $this->assertSame('no-query-in-loop', $s->visibleDiagnostics()[0]->ruleId);
    }
}
