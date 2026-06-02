<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Architecture;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

final class NoBusinessLogicInRouteClosure implements Rule
{
    private const MAX_CLOSURE_STMTS = 5;
    private const ROUTE_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'any', 'match', 'options'];

    public function id(): string { return 'no-business-logic-in-route-closure'; }

    public function title(): string { return 'Lógica de negocio en una closure de ruta'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Info; }

    public function recommendation(): string
    {
        return 'Mueve la lógica a un controller (Route::get(..., [Controller::class, "método"])); las closures de ruta no son testeables ni cacheables con route:cache.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof Closure) {
            return;
        }
        if (count($node->stmts) <= self::MAX_CLOSURE_STMTS) {
            return;
        }
        if (!str_contains(str_replace('\\', '/', $context->filePath()), 'routes/')) {
            return;
        }
        if (!$this->isRouteRegistration($context->ancestors())) {
            return;
        }

        $context->report($node, 'Closure de ruta con más de ' . self::MAX_CLOSURE_STMTS . ' sentencias: la lógica debería vivir en un controller.');
    }

    /** @param Node[] $ancestors */
    private function isRouteRegistration(array $ancestors): bool
    {
        foreach ($ancestors as $ancestor) {
            if ($ancestor instanceof StaticCall
                && $ancestor->class instanceof Name
                && $ancestor->class->getLast() === 'Route'
                && $ancestor->name instanceof Identifier
                && in_array($ancestor->name->toString(), self::ROUTE_METHODS, true)) {
                return true;
            }
        }

        return false;
    }
}
