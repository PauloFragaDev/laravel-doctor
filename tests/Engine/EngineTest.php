<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Engine;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use LaravelDoctor\Scanner\SourceFile;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    private function echoRule(): Rule
    {
        return new class implements Rule {
            public function id(): string { return 'reports-echo'; }
            public function title(): string { return 'echo'; }
            public function category(): string { return Categories::ARCHITECTURE; }
            public function severity(): Severity { return Severity::Info; }
            public function recommendation(): string { return 'no uses echo'; }
            public function enterNode(Node $node, RuleContext $context): void
            {
                if ($node instanceof \PhpParser\Node\Stmt\Echo_) {
                    $context->report($node, 'echo encontrado');
                }
            }
        };
    }

    public function test_runs_rules_across_files(): void
    {
        $engine = new Engine([$this->echoRule()]);
        $diagnostics = $engine->inspect([
            new SourceFile('a.php', "<?php echo 'hi';"),
            new SourceFile('b.php', "<?php \$x = 1;"),
        ]);

        $this->assertCount(1, $diagnostics);
        $this->assertSame('reports-echo', $diagnostics[0]->ruleId);
        $this->assertSame('a.php', $diagnostics[0]->file);
    }

    public function test_unparseable_file_is_skipped_silently_and_others_continue(): void
    {
        $engine = new Engine([$this->echoRule()]);
        $diagnostics = $engine->inspect([
            new SourceFile('broken.php', "<?php echo ;"),
            new SourceFile('ok.php', "<?php echo 'hi';"),
        ]);

        $ids = array_map(fn ($d) => $d->ruleId, $diagnostics);
        // El archivo roto se salta sin emitir 'parse-error'; el bueno se analiza igual.
        $this->assertNotContains('parse-error', $ids);
        $this->assertContains('reports-echo', $ids);
        $this->assertCount(1, $diagnostics);
    }
}
