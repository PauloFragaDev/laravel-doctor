<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

use LaravelDoctor\Diagnostics\Diagnostic;

/**
 * Suprime los hallazgos ya presentes en la línea base, dejando pasar solo los NUEVOS. Por cada
 * (regla, archivo) descarta hasta `count` hallazgos; si aparecen más, los extra (nuevos) se ven.
 */
final class BaselineApplier
{
    /**
     * @param Diagnostic[] $diagnostics
     * @return Diagnostic[]
     */
    public function apply(array $diagnostics, Baseline $baseline, string $projectDir): array
    {
        if ($baseline->isEmpty()) {
            return $diagnostics;
        }

        $remaining = $baseline->counts;
        $out = [];
        foreach ($diagnostics as $d) {
            $key = Baseline::key($d->ruleId, $this->relative($d->file, $projectDir));
            if (($remaining[$key] ?? 0) > 0) {
                $remaining[$key]--;
                continue;
            }
            $out[] = $d;
        }

        return $out;
    }

    private function relative(string $file, string $projectDir): string
    {
        $base = rtrim($projectDir, '/') . '/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
