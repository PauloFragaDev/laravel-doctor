<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Security;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\InterpolatedString;

final class NoRawSqlInterpolation implements Rule
{
    private const RAW_METHODS = [
        'whereRaw', 'havingRaw', 'orderByRaw', 'selectRaw', 'raw', 'statement', 'unprepared',
    ];

    public function id(): string { return 'no-raw-sql-interpolation'; }

    public function title(): string { return 'SQL crudo con interpolación de variables'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Usa bindings parametrizados (segundo argumento de whereRaw / "?") en vez de interpolar variables en el SQL: evita inyección.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return;
        }
        if (!$node->name instanceof Identifier || !in_array($node->name->toString(), self::RAW_METHODS, true)) {
            return;
        }
        $first = $node->args[0] ?? null;
        if ($first === null || !$first instanceof Node\Arg) {
            return;
        }

        if ($this->isInterpolated($first->value)) {
            $context->report($node, 'SQL crudo con una variable interpolada: vector de inyección SQL.');
        }
    }

    private function isInterpolated(Node $value): bool
    {
        if ($value instanceof InterpolatedString) {
            return true;
        }
        if ($value instanceof Concat) {
            return $this->hasVariable($value);
        }

        return false;
    }

    private function hasVariable(Node $node): bool
    {
        if ($node instanceof Variable) {
            return true;
        }
        if ($node instanceof Concat) {
            return $this->hasVariable($node->left) || $this->hasVariable($node->right);
        }

        return false;
    }
}
