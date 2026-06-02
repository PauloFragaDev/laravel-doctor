<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
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
    public function inspect(array $files, ?array $runtimeManifest = null): array
    {
        $collector = new DiagnosticCollector();
        $visitor = new RuleVisitor($this->rules, $collector);

        foreach ($files as $file) {
            try {
                $stmts = $this->parser->parse($file->contents);
            } catch (Error $e) {
                $collector->add(new Diagnostic(
                    ruleId: 'parse-error',
                    category: Categories::ARCHITECTURE,
                    severity: Severity::Info,
                    file: $file->path,
                    line: $e->getStartLine() > 0 ? $e->getStartLine() : 1,
                    message: 'No se pudo analizar este archivo: ' . $e->getRawMessage(),
                    recommendation: 'Corrige el error de sintaxis para que laravel-doctor pueda revisarlo.',
                ));
                continue;
            }

            $visitor->setFile($file->path);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);
        }

        return $collector->all();
    }
}
