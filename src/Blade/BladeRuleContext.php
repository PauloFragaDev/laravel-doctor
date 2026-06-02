<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;

final class BladeRuleContext
{
    public function __construct(
        private BladeRule $rule,
        private string $file,
        private DiagnosticCollector $collector,
    ) {
    }

    public function report(BladeConstruct $construct, string $message): void
    {
        $this->collector->add(new Diagnostic(
            ruleId: $this->rule->id(),
            category: $this->rule->category(),
            severity: $this->rule->severity(),
            file: $this->file,
            line: $construct->line,
            message: $message,
            recommendation: $this->rule->recommendation(),
        ));
    }
}
