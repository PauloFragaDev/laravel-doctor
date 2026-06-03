<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Architecture;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

final class NoFatControllerMethod implements Rule
{
    private const MAX_METHOD_LINES = 60;

    public function id(): string { return 'no-fat-controller-method'; }

    public function title(): string { return 'Método de controller demasiado largo'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Extrae la lógica a una Action, un servicio o el modelo; el controller debería orquestar, no contener la lógica de negocio.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof ClassMethod) {
            return;
        }
        if (!$this->isInsideController($context->ancestors())) {
            return;
        }
        if (($node->getEndLine() - $node->getStartLine()) > self::MAX_METHOD_LINES) {
            $context->report($node, 'Método de controller de más de ' . self::MAX_METHOD_LINES . ' líneas: demasiada lógica para un controller.');
        }
    }

    /** @param Node[] $ancestors */
    private function isInsideController(array $ancestors): bool
    {
        foreach ($ancestors as $ancestor) {
            if ($ancestor instanceof Class_
                && $ancestor->name !== null
                && str_ends_with($ancestor->name->toString(), 'Controller')) {
                return true;
            }
        }

        return false;
    }
}
