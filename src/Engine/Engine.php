<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Runtime\RuntimeManifest;
use LaravelDoctor\Scanner\SourceFile;
use PhpParser\Error;
use PhpParser\NodeTraverser;

final class Engine
{
    private PhpAstParser $parser;

    /**
     * @param Rule[] $rules
     */
    public function __construct(private array $rules)
    {
        $this->parser = new PhpAstParser();
    }

    /**
     * @param SourceFile[] $files
     * @return Diagnostic[]
     */
    public function inspect(array $files, ?RuntimeManifest $runtimeManifest = null): array
    {
        $collector = new DiagnosticCollector();
        $visitor = new RuleVisitor($this->rules, $collector);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);

        foreach ($files as $file) {
            try {
                $stmts = $this->parser->parse($file->contents);
            } catch (Error) {
                // Archivo no parseable (a menudo libs de terceros con sintaxis antigua): se
                // salta en silencio. No es un hallazgo accionable en el código del usuario.
                continue;
            }

            $visitor->setFile($file->path);
            $traverser->traverse($stmts);
        }

        return $collector->all();
    }
}
