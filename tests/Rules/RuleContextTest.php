<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\AncestorProvider;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\TestCase;

final class RuleContextTest extends TestCase
{
    private function fakeRule(): Rule
    {
        return new class implements Rule {
            public function id(): string { return 'fake-rule'; }
            public function title(): string { return 'Fake'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Error; }
            public function recommendation(): string { return 'arregla esto'; }
            public function enterNode(Node $node, RuleContext $context): void {}
        };
    }

    private function provider(string $file, array $ancestors): AncestorProvider
    {
        return new class($file, $ancestors) implements AncestorProvider {
            public function __construct(private string $file, private array $ancestors) {}
            public function currentFile(): string { return $this->file; }
            public function currentAncestors(): array { return $this->ancestors; }
        };
    }

    public function test_report_builds_diagnostic_from_rule_metadata(): void
    {
        $collector = new DiagnosticCollector();
        $ctx = new RuleContext($this->fakeRule(), $this->provider('app/X.php', []), $collector);

        $node = new Int_(0);
        $node->setAttribute('startLine', 7);
        $ctx->report($node, 'mensaje de impacto');

        $all = $collector->all();
        $this->assertCount(1, $all);
        $this->assertSame('fake-rule', $all[0]->ruleId);
        $this->assertSame('security', $all[0]->category);
        $this->assertSame(Severity::Error, $all[0]->severity);
        $this->assertSame('app/X.php', $all[0]->file);
        $this->assertSame(7, $all[0]->line);
        $this->assertSame('mensaje de impacto', $all[0]->message);
        $this->assertSame('arregla esto', $all[0]->recommendation);
    }

    public function test_ancestors_are_exposed(): void
    {
        $parent = new Int_(1);
        $ctx = new RuleContext($this->fakeRule(), $this->provider('app/X.php', [$parent]), new DiagnosticCollector());
        $this->assertSame([$parent], $ctx->ancestors());
    }
}
