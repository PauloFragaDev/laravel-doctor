<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class PhpAstParser
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return Stmt[]
     * @throws \PhpParser\Error en error de sintaxis
     */
    public function parse(string $code): array
    {
        return $this->parser->parse($code) ?? [];
    }
}
