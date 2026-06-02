<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;

final class ManifestRuleContext
{
    public function __construct(
        private ManifestRule $rule,
        private DiagnosticCollector $collector,
    ) {
    }

    public function report(string $file, int $line, string $message): void
    {
        $this->collector->add(new Diagnostic(
            ruleId: $this->rule->id(),
            category: $this->rule->category(),
            severity: $this->rule->severity(),
            file: $file,
            line: $line,
            message: $message,
            recommendation: $this->rule->recommendation(),
        ));
    }
}
