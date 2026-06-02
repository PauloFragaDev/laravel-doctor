<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use PhpParser\Node;

interface AncestorProvider
{
    public function currentFile(): string;

    /** Ancestros del nodo en curso, del más cercano al más lejano. @return Node[] */
    public function currentAncestors(): array;
}
