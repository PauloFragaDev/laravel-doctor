<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Performance;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;

final class NoAllThenFilter implements Rule
{
    private const COLLECTION_METHODS = ['filter', 'where', 'map', 'reject', 'sortBy'];

    public function id(): string { return 'no-all-then-filter'; }

    public function title(): string { return 'all() seguido de filtrado en PHP'; }

    public function category(): string { return Categories::PERFORMANCE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Filtra en la base de datos con where()/whereIn() antes de traer los datos; all()->filter() carga toda la tabla en memoria.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if (!in_array($node->name->toString(), self::COLLECTION_METHODS, true)) {
            return;
        }

        $receiver = $node->var;
        $isAll = ($receiver instanceof MethodCall || $receiver instanceof StaticCall)
            && $receiver->name instanceof Identifier
            && $receiver->name->toString() === 'all';

        if ($isAll) {
            $context->report($node, 'all()->' . $node->name->toString() . '() trae toda la tabla y filtra en PHP.');
        }
    }
}
