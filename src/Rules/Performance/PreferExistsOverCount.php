<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Performance;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;

final class PreferExistsOverCount implements Rule
{
    public function id(): string { return 'prefer-exists-over-count'; }

    public function title(): string { return 'count() > 0 para comprobar existencia'; }

    public function category(): string { return Categories::PERFORMANCE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Usa ->exists() en vez de ->count() > 0: la DB para en cuanto encuentra una fila en lugar de contarlas todas.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof Greater) {
            return;
        }
        if (!$node->right instanceof Int_ || $node->right->value !== 0) {
            return;
        }
        $left = $node->left;
        if (!$left instanceof MethodCall || !$left->name instanceof Identifier) {
            return;
        }
        if ($left->name->toString() !== 'count') {
            return;
        }

        $context->report($node, 'count() > 0 cuenta todas las filas; ->exists() para en la primera coincidencia.');
    }
}
