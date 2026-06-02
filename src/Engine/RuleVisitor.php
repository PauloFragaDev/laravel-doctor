<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Rules\AncestorProvider;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class RuleVisitor extends NodeVisitorAbstract implements AncestorProvider
{
    private string $file = '';

    /** @var Node[] pila de ancestros, el último es el padre inmediato */
    private array $stack = [];

    /** @var array<string,RuleContext> contexto reutilizable por id de regla */
    private array $contexts = [];

    /** @var Rule[] */
    private array $rules;

    /**
     * @param Rule[] $rules
     */
    public function __construct(array $rules, private DiagnosticCollector $collector)
    {
        $this->rules = $rules;
        foreach ($rules as $rule) {
            $this->contexts[$rule->id()] = new RuleContext($rule, $this, $collector);
        }
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
        $this->stack = [];
    }

    public function enterNode(Node $node)
    {
        foreach ($this->rules as $rule) {
            try {
                $rule->enterNode($node, $this->contexts[$rule->id()]);
            } catch (\Throwable) {
                // Aislamiento por regla: una regla rota nunca tumba el run.
            }
        }
        $this->stack[] = $node;

        return null;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->stack);

        return null;
    }

    public function currentFile(): string
    {
        return $this->file;
    }

    public function currentAncestors(): array
    {
        // Más cercano primero.
        return array_reverse($this->stack);
    }
}
