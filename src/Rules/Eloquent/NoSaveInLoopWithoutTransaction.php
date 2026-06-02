<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Eloquent;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;

final class NoSaveInLoopWithoutTransaction implements Rule
{
    private const LOOP_NODES = [Foreach_::class, For_::class, While_::class, Do_::class];
    private const WRITE_METHODS = ['save', 'update'];

    public function id(): string { return 'no-save-in-loop-without-transaction'; }

    public function title(): string { return 'Escritura Eloquent en bucle sin transacción'; }

    public function category(): string { return Categories::ELOQUENT; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Envuelve el bucle en DB::transaction(): N escrituras sueltas son lentas y dejan datos inconsistentes si una falla a medias.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if (!in_array($node->name->toString(), self::WRITE_METHODS, true)) {
            return;
        }

        $ancestors = $context->ancestors();
        if (!$this->hasLoopAncestor($ancestors) || $this->hasTransactionAncestor($ancestors)) {
            return;
        }

        $context->report($node, 'Escritura en bucle sin DB::transaction(): lento e inconsistente ante fallos parciales.');
    }

    /** @param Node[] $ancestors */
    private function hasLoopAncestor(array $ancestors): bool
    {
        foreach ($ancestors as $a) {
            if (in_array($a::class, self::LOOP_NODES, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param Node[] $ancestors */
    private function hasTransactionAncestor(array $ancestors): bool
    {
        foreach ($ancestors as $a) {
            $name = null;
            if ($a instanceof StaticCall && $a->name instanceof Identifier) {
                $name = $a->name->toString();
            } elseif ($a instanceof MethodCall && $a->name instanceof Identifier) {
                $name = $a->name->toString();
            }
            if ($name === 'transaction') {
                return true;
            }
        }

        return false;
    }
}
