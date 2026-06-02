<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final class DiagnosticCollector
{
    /** @var Diagnostic[] */
    private array $diagnostics = [];

    public function add(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /** @return Diagnostic[] */
    public function all(): array
    {
        return $this->diagnostics;
    }
}
