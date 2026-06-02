<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;

final class ManifestEngine
{
    /**
     * @param ManifestRule[] $rules
     */
    public function __construct(private array $rules)
    {
    }

    /**
     * @return Diagnostic[]
     */
    public function inspect(RuntimeManifest $manifest): array
    {
        $collector = new DiagnosticCollector();

        foreach ($this->rules as $rule) {
            try {
                $rule->check($manifest, new ManifestRuleContext($rule, $collector));
            } catch (\Throwable) {
                // Aislamiento por regla: una regla rota no tumba el run.
            }
        }

        return $collector->all();
    }
}
