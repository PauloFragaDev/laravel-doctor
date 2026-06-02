<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Scanner\SourceFile;

final class BladeEngine
{
    private BladeScanner $scanner;

    /**
     * @param BladeRule[] $rules
     */
    public function __construct(private array $rules)
    {
        $this->scanner = new BladeScanner();
    }

    /**
     * @param SourceFile[] $files
     * @return Diagnostic[]
     */
    public function inspect(array $files): array
    {
        $collector = new DiagnosticCollector();

        foreach ($files as $file) {
            $contexts = [];
            foreach ($this->rules as $rule) {
                $contexts[$rule->id()] = new BladeRuleContext($rule, $file->path, $collector);
            }

            foreach ($this->scanner->scan($file->contents) as $construct) {
                foreach ($this->rules as $rule) {
                    try {
                        $rule->enterConstruct($construct, $contexts[$rule->id()]);
                    } catch (\Throwable) {
                        // Aislamiento por regla: una regla rota no tumba el run.
                    }
                }
            }
        }

        return $collector->all();
    }
}
