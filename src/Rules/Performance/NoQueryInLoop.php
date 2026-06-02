<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Performance;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;

final class NoQueryInLoop implements Rule
{
    private const LOOP_NODES = [Foreach_::class, For_::class, While_::class, Do_::class];
    private const QUERY_METHODS = ['get', 'first', 'find', 'count', 'pluck', 'paginate', 'sum', 'value'];

    public function id(): string { return 'no-query-in-loop'; }

    public function title(): string { return 'Consulta a la base de datos dentro de un bucle'; }

    public function category(): string { return Categories::PERFORMANCE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Carga los datos antes del bucle (eager loading con with(), o whereIn()): una query por iteración es el patrón N+1.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if (!in_array($node->name->toString(), self::QUERY_METHODS, true)) {
            return;
        }
        foreach ($context->ancestors() as $ancestor) {
            if (in_array($ancestor::class, self::LOOP_NODES, true)) {
                $context->report($node, 'Consulta dentro de un bucle: provoca N+1 (una query por iteración).');

                return;
            }
        }
    }
}
