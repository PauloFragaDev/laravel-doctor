<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final class Pipeline
{
    /**
     * @param Diagnostic[] $diagnostics
     * @return Diagnostic[]
     */
    public function process(array $diagnostics): array
    {
        $deduped = $this->dedupe($diagnostics);
        usort($deduped, $this->comparator(...));

        return $deduped;
    }

    /**
     * @param Diagnostic[] $diagnostics
     * @return Diagnostic[]
     */
    private function dedupe(array $diagnostics): array
    {
        $seen = [];
        $out = [];
        foreach ($diagnostics as $d) {
            $key = $d->ruleId . '|' . $d->file . '|' . $d->line;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $d;
        }

        return $out;
    }

    private function comparator(Diagnostic $a, Diagnostic $b): int
    {
        return $b->severity->weight() <=> $a->severity->weight()
            ?: Categories::weight($b->category) <=> Categories::weight($a->category)
            ?: strcmp($a->file, $b->file)
            ?: $a->line <=> $b->line;
    }
}
