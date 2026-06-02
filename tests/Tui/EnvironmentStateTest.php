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

    private function d(string $rule, string $cat): Diagnostic
    {
        return new Diagnostic($rule, $cat, Severity::Error, 'app/X.php', 1, 'm', 'r');
    }

    private function withResults(): EnvironmentState
    {
        $s = $this->state();
        $s->openResults('shop', [
            $this->d('no-env-outside-config', Categories::SECURITY),
            $this->d('no-raw-sql-interpolation', Categories::SECURITY),
            $this->d('no-query-in-loop', Categories::PERFORMANCE),
        ], new ScoreResult(70, 'Needs work'));

        return $s;
    }

    public function test_live_search_filters_projects(): void
    {
        $s = $this->state();
        foreach (str_split('shop') as $c) {
            $s->appendSearch($c);
        }
        $this->assertSame(['shop', 'shopify-bridge'], array_map(fn ($p) => $p->name, $s->visibleProjects()));
    }

    public function test_grouped_rows_have_category_headers(): void
    {
        $rows = $this->withResults()->groupedRows();
        $kinds = array_map(fn ($r) => $r['kind'], $rows);

        // header, finding, finding, header, finding
        $this->assertSame(['header', 'finding', 'finding', 'header', 'finding'], $kinds);
        $this->assertSame('Seguridad', $rows[0]['label']);
        $this->assertSame(2, $rows[0]['count']);
        $this->assertSame('Performance', $rows[3]['label']);
    }

    public function test_category_filter_narrows_to_one_block(): void
    {
        $s = $this->withResults();
        $s->cycleCategory(); // all -> security
        $this->assertSame(Categories::SECURITY, $s->category);
        $this->assertCount(2, $s->visibleFindings());
        $kinds = array_map(fn ($r) => $r['kind'], $s->groupedRows());
        $this->assertSame(['header', 'finding', 'finding'], $kinds);
    }

    public function test_selected_row_index_skips_headers(): void
    {
        $s = $this->withResults();
        $s->moveDown();
        $s->moveDown(); // tercer hallazgo (no-query-in-loop, en bloque Performance)
        $this->assertSame('no-query-in-loop', $s->selectedDiagnostic()->ruleId);
        // groupedRows: [h,f,f,h,f] → el tercer finding está en índice 4.
        $this->assertSame(4, $s->selectedRowIndex());
    }

    public function test_search_filters_findings(): void
    {
        $s = $this->withResults();
        foreach (str_split('query') as $c) {
            $s->appendSearch($c);
        }
        $this->assertCount(1, $s->visibleFindings());
        $this->assertSame('no-query-in-loop', $s->visibleFindings()[0]->ruleId);
    }
}
