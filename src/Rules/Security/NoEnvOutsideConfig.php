<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Security;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

final class NoEnvOutsideConfig implements Rule
{
    public function id(): string { return 'no-env-outside-config'; }

    public function title(): string { return 'Uso de env() fuera de config'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Mueve el valor a un archivo de config/ y léelo con config(); env() devuelve null cuando la config está cacheada.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return;
        }
        if ($node->name->toString() !== 'env') {
            return;
        }
        if ($this->isInsideConfig($context->filePath())) {
            return;
        }

        $context->report($node, 'env() fuera de config/ devuelve null con la config cacheada en producción.');
    }

    private function isInsideConfig(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_contains($normalized, '/config/') || str_starts_with($normalized, 'config/');
    }
}
