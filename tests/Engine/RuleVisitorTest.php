<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Engine;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Engine\PhpAstParser;
use LaravelDoctor\Engine\RuleVisitor;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeTraverser;
use PHPUnit\Framework\TestCase;

final class RuleVisitorTest extends TestCase
{
    /** Regla que reporta cada FuncCall y registra si tenía un foreach como ancestro. */
    private function recordingRule(array &$seenAncestorTypes): Rule
    {
        return new class($seenAncestorTypes) implements Rule {
            public function __construct(private array &$seen) {}
            public function id(): string { return 'rec'; }
            public function title(): string { return 'rec'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'fix'; }
            public function enterNode(Node $node, RuleContext $context): void
            {
                if ($node instanceof \PhpParser\Node\Expr\FuncCall) {
                    foreach ($context->ancestors() as $a) {
                        $this->seen[] = $a::class;
                    }
                    $context->report($node, 'func call');
                }
            }
        };
    }

    public function test_dispatches_rules_and_tracks_ancestors(): void
    {
        $seen = [];
        $collector = new DiagnosticCollector();
        $visitor = new RuleVisitor([$this->recordingRule($seen)], $collector);
        $visitor->setFile('app/Demo.php');

        $stmts = (new PhpAstParser())->parse("<?php foreach (\$xs as \$x) { strlen(\$x); }");
        $t = new NodeTraverser();
        $t->addVisitor($visitor);
        $t->traverse($stmts);

        // El FuncCall strlen() está dentro de un foreach → su ancestro incluye Foreach_.
        $this->assertContains(Foreach_::class, $seen);
        $this->assertCount(1, $collector->all());
        $this->assertSame('app/Demo.php', $collector->all()[0]->file);
    }

    public function test_isolates_rule_exceptions(): void
    {
        $throwing = new class implements Rule {
            public function id(): string { return 'boom'; }
            public function title(): string { return 'boom'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Error; }
            public function recommendation(): string { return 'x'; }
            public function enterNode(Node $node, RuleContext $context): void
            {
                throw new \RuntimeException('regla rota');
            }
        };

        $collector = new DiagnosticCollector();
        $visitor = new RuleVisitor([$throwing], $collector);
        $visitor->setFile('app/Demo.php');

        $stmts = (new PhpAstParser())->parse("<?php \$x = 1;");
        $t = new NodeTraverser();
        $t->addVisitor($visitor);

        // No debe propagar la excepción.
        $t->traverse($stmts);
        $this->assertSame([], $collector->all());
    }
}
