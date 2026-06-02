<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

use LaravelDoctor\Diagnostics\Diagnostic;

final class ConfigApplier
{
    /**
     * Aplica la config a los diagnósticos: descarta reglas desactivadas y rutas excluidas,
     * y restampa la severidad de las reglas con override.
     *
     * @param Diagnostic[] $diagnostics
     * @return Diagnostic[]
     */
    public function apply(array $diagnostics, DoctorConfig $config): array
    {
        $out = [];
        foreach ($diagnostics as $d) {
            if (in_array($d->ruleId, $config->disabled, true)) {
                continue;
            }
            if ($this->isExcluded($d->file, $config->exclude)) {
                continue;
            }

            $override = $config->severityOverrides[$d->ruleId] ?? null;
            if ($override !== null && $override !== $d->severity) {
                $out[] = new Diagnostic(
                    ruleId: $d->ruleId,
                    category: $d->category,
                    severity: $override,
                    file: $d->file,
                    line: $d->line,
                    message: $d->message,
                    recommendation: $d->recommendation,
                );
                continue;
            }

            $out[] = $d;
        }

        return $out;
    }

    /**
     * @param string[] $globs
     */
    private function isExcluded(string $file, array $globs): bool
    {
        $normalized = str_replace('\\', '/', $file);
        foreach ($globs as $glob) {
            if (fnmatch($glob, $normalized) || fnmatch('*/' . ltrim($glob, '/'), $normalized)) {
                return true;
            }
        }

        return false;
    }
}
