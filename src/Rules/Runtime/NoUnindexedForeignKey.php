<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Runtime;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Runtime\ManifestRule;
use LaravelDoctor\Runtime\ManifestRuleContext;
use LaravelDoctor\Runtime\RuntimeManifest;

final class NoUnindexedForeignKey implements ManifestRule
{
    public function id(): string { return 'no-unindexed-foreign-key'; }

    public function title(): string { return 'Columna foránea (*_id) sin índice'; }

    public function category(): string { return Categories::PERFORMANCE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Añade un índice a la columna foránea (p. ej. $table->foreignId(...)->constrained() o $table->index()); sin índice, los joins y lookups por esa columna escanean toda la tabla.';
    }

    public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void
    {
        foreach ($manifest->models as $model) {
            foreach ($model->columns as $column => $type) {
                if (!$this->looksLikeForeignKey($column, $type)) {
                    continue;
                }
                if (in_array($column, $model->indexes, true)) {
                    continue;
                }

                $context->report(
                    $model->class,
                    0,
                    sprintf('La columna foránea "%s" de %s no tiene índice: lookups y joins lentos.', $column, $model->table),
                );
            }
        }
    }

    private function looksLikeForeignKey(string $column, string $type): bool
    {
        return str_ends_with($column, '_id') && str_contains(strtolower($type), 'int');
    }
}
