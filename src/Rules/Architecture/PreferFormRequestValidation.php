<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Architecture;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

final class PreferFormRequestValidation implements Rule
{
    public function id(): string { return 'prefer-form-request-validation'; }

    public function title(): string { return 'Validación inline en el controller'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Info; }

    public function recommendation(): string
    {
        return 'Extrae la validación a un Form Request: deja el controller delgado y reutiliza las reglas.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if ($node->name->toString() !== 'validate') {
            return;
        }
        if (!$node->var instanceof Variable || $node->var->name !== 'request') {
            return;
        }

        $context->report($node, '$request->validate() inline acopla validación y controller; un Form Request lo separa.');
    }
}
