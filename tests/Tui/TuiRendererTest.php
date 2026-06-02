<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Tui;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreResult;
use LaravelDoctor\Tui\ProjectInfo;
use LaravelDoctor\Tui\TuiRenderer;
use LaravelDoctor\Tui\TuiState;
use PHPUnit\Framework\TestCase;

final class TuiRendererTest extends TestCase
{
    public function test_projects_screen_lists_projects_with_marker(): void
    {
        $state = new TuiState([new ProjectInfo('blog', '/p/blog'), new ProjectInfo('shop', '/p/shop')]);
        $out = (new TuiRenderer())->render($state, 100);

        $this->assertStringContainsString('blog', $out);
        $this->assertStringContainsString('shop', $out);
        $this->assertStringContainsString('› blog', $out);          // primer proyecto seleccionado
        $this->assertStringContainsString('Enter abrir', $out);     // footer del panel de proyectos
    }

    public function test_results_screen_shows_findings_and_score(): void
    {
        $state = new TuiState([new ProjectInfo('blog', '/p/blog')]);
        $state->openProject();
        $state->setResults('/p/blog', [
            new Diagnostic('no-env-outside-config', Categories::SECURITY, Severity::Error, 'app/Pay.php', 12, 'm', 'r'),
        ], new ScoreResult(88, 'Needs work'));

        $out = (new TuiRenderer())->render($state, 100);

        $this->assertStringContainsString('Score 88/100', $out);
        $this->assertStringContainsString('no-env-outside-config', $out);
        $this->assertStringContainsString('app/Pay.php:12', $out);
        $this->assertStringContainsString('› ERROR', $out);
    }

    public function test_expanded_detail_shows_recommendation(): void
    {
        $state = new TuiState([new ProjectInfo('blog', '/p/blog')]);
        $state->openProject();
        $state->setResults('/p/blog', [
            new Diagnostic('r', Categories::SECURITY, Severity::Error, 'a.php', 1, 'el mensaje', 'la recomendación'),
        ], new ScoreResult(88, 'Needs work'));
        $state->toggleExpand();

        $out = (new TuiRenderer())->render($state, 100);
        $this->assertStringContainsString('el mensaje', $out);
        $this->assertStringContainsString('→ la recomendación', $out);
    }

    public function test_search_footer(): void
    {
        $state = new TuiState([new ProjectInfo('blog', '/p/blog')]);
        $state->openProject();
        $state->startSearch();
        $state->appendSearch('env');
        $out = (new TuiRenderer())->render($state, 100);
        $this->assertStringContainsString('buscar: env', $out);
    }
}
