<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Security;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

final class NoHardcodedCredentials implements Rule
{
    private const SECRET_KEYWORDS = [
        'password', 'passwd', 'secret', 'apikey', 'api_key', 'token', 'privatekey', 'private_key',
    ];

    public function id(): string { return 'no-hardcoded-credentials'; }

    public function title(): string { return 'Credencial hardcodeada'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Mueve el secreto a una variable de entorno (.env) y léelo desde config(); nunca lo dejes literal en el código (acaba en git).';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof Assign || !$node->expr instanceof String_) {
            return;
        }
        // Ignora valores en blanco/placeholder (p. ej. '' o ' ').
        if (trim($node->expr->value) === '') {
            return;
        }

        $name = $this->targetName($node->var);
        if ($name !== null && $this->looksLikeSecret($name)) {
            $context->report($node, sprintf('"%s" parece una credencial asignada como literal en el código.', $name));
        }
    }

    private function targetName(Node $target): ?string
    {
        if ($target instanceof Variable && is_string($target->name)) {
            return $target->name;
        }
        if ($target instanceof PropertyFetch && $target->name instanceof Identifier) {
            return $target->name->toString();
        }

        return null;
    }

    private function looksLikeSecret(string $name): bool
    {
        $normalized = strtolower(str_replace('_', '', $name));
        foreach (self::SECRET_KEYWORDS as $keyword) {
            if (str_contains($normalized, str_replace('_', '', $keyword))) {
                return true;
            }
        }

        return false;
    }
}
