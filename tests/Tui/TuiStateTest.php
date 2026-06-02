<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Tui;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreResult;
use LaravelDoctor\Tui\ProjectInfo;
use LaravelDoctor\Tui\TuiState;
use PHPUnit\Framework\TestCase;

final class TuiStateTest extends TestCase
{
    private function state(): TuiState
    {
        return new TuiState([
            new ProjectInfo('blog', '/p/blog'),
            new ProjectInfo('shop', '/p/shop'),
        ]);
    }

    private function d(string $rule, string $cat): Diagnostic
    {
        return new Diagnostic($rule, $cat, Severity::Warning, 'app/X.php', 1, 'm', 'r');
    }

    public function test_project_navigation_is_clamped(): void
    {
        $s = $this->state();
        $s->moveUp();
        $this->assertSame(0, $s->projectIndex);
        $s->moveDown();
        $s->moveDown();
        $s->moveDown(); // más allá del final
        $this->assertSame(1, $s->projectIndex);
        $this->assertSame('shop', $s->currentProject()->name);
    }

    public function test_open_project_switches_screen(): void
    {
        $s = $this->state();
        $s->openProject();
        $this->assertSame(TuiState::SCREEN_RESULTS, $s->screen);
        $s->back();
        $this->assertSame(TuiState::SCREEN_PROJECTS, $s->screen);
    }

    public function test_category_filter(): void
    {
        $s = $this->state();
        $s->openProject();
        $s->setResults('/p/blog', [
            $this->d('a', Categories::SECURITY),
            $this->d('b', Categories::PERFORMANCE),
        ], new ScoreResult(80, 'Needs work'));

        $this->assertCount(2, $s->visibleDiagnostics());

        $s->cycleCategory(); // all -> security
        $this->assertSame('security', $s->category);
        $this->assertCount(1, $s->visibleDiagnostics());
        $this->assertSame('a', $s->visibleDiagnostics()[0]->ruleId);
    }

    public function test_search_filters_and_resets_index(): void
    {
        $s = $this->state();
        $s->openProject();
        $s->setResults('/p/blog', [
            $this->d('no-env-outside-config', Categories::SECURITY),
            $this->d('no-query-in-loop', Categories::PERFORMANCE),
        ], new ScoreResult(80, 'Needs work'));

        $s->startSearch();
        foreach (str_split('query') as $c) {
            $s->appendSearch($c);
        }
        $this->assertCount(1, $s->visibleDiagnostics());
        $this->assertSame('no-query-in-loop', $s->visibleDiagnostics()[0]->ruleId);

        $s->backspaceSearch();
        $s->backspaceSearch();
        $s->backspaceSearch();
        $s->backspaceSearch();
        $s->backspaceSearch();
        $this->assertCount(2, $s->visibleDiagnostics());
    }

    public function test_selected_diagnostic_follows_index(): void
    {
        $s = $this->state();
        $s->openProject();
        $s->setResults('/p/blog', [
            $this->d('a', Categories::SECURITY),
            $this->d('b', Categories::SECURITY),
        ], new ScoreResult(80, 'Needs work'));

        $this->assertSame('a', $s->selectedDiagnostic()->ruleId);
        $s->moveDown();
        $this->assertSame('b', $s->selectedDiagnostic()->ruleId);
    }
}
