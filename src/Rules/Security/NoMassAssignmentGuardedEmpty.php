<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Security;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Property;

final class NoMassAssignmentGuardedEmpty implements Rule
{
    public function id(): string { return 'no-mass-assignment-guarded-empty'; }

    public function title(): string { return 'Asignación en masa sin restricción'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Define $fillable con los campos permitidos en vez de $guarded = [] / unguard(); así el request no puede escribir columnas sensibles.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if ($node instanceof Property) {
            foreach ($node->props as $item) {
                if ($item->name->toString() === 'guarded'
                    && $item->default instanceof Array_
                    && $item->default->items === []) {
                    $context->report($node, '$guarded = [] deja todos los campos asignables en masa.');

                    return;
                }
            }

            return;
        }

        if (($node instanceof StaticCall || $node instanceof MethodCall)
            && $node->name instanceof Identifier
            && $node->name->toString() === 'unguard') {
            $context->report($node, 'unguard() desactiva la protección de asignación en masa globalmente.');
        }
    }
}
