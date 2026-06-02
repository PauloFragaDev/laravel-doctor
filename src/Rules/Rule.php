<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Diagnostics\Severity;
use PhpParser\Node;

interface Rule
{
    public function id(): string;

    public function title(): string;

    /** Una de las constantes de LaravelDoctor\Diagnostics\Categories. */
    public function category(): string;

    public function severity(): Severity;

    public function recommendation(): string;

    /** Invocado para cada nodo del AST durante el recorrido. */
    public function enterNode(Node $node, RuleContext $context): void;
}
