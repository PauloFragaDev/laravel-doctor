<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use PhpParser\Node;

final class RuleContext
{
    public function __construct(
        private Rule $rule,
        private AncestorProvider $provider,
        private DiagnosticCollector $collector,
    ) {
    }

    public function report(Node $node, string $message): void
    {
        $this->collector->add(new Diagnostic(
            ruleId: $this->rule->id(),
            category: $this->rule->category(),
            severity: $this->rule->severity(),
            file: $this->provider->currentFile(),
            line: $node->getStartLine(),
            message: $message,
            recommendation: $this->rule->recommendation(),
        ));
    }

    /** @return Node[] del más cercano al más lejano */
    public function ancestors(): array
    {
        return $this->provider->currentAncestors();
    }

    public function filePath(): string
    {
        return $this->provider->currentFile();
    }
}
