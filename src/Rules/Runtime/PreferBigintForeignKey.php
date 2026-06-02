<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Runtime;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Runtime\ManifestRule;
use LaravelDoctor\Runtime\ManifestRuleContext;
use LaravelDoctor\Runtime\RuntimeManifest;

final class PreferBigintForeignKey implements ManifestRule
{
    public function id(): string { return 'prefer-bigint-foreign-key'; }

    public function title(): string { return 'Columna foránea (*_id) que no es bigint'; }

    public function category(): string { return Categories::ELOQUENT; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Usa bigint para las columnas foráneas (foreignId()): las claves primarias id de Laravel son bigint por defecto, y un tipo más pequeño rompe la relación o desborda.';
    }

    public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void
    {
        foreach ($manifest->models as $model) {
            foreach ($model->columns as $column => $type) {
                if (!str_ends_with($column, '_id')) {
                    continue;
                }
                if (!$this->isUndersizedInteger($type)) {
                    continue;
                }

                $context->report(
                    $model->class,
                    0,
                    sprintf('La columna foránea "%s" de %s es %s, no bigint: no casa con el id por defecto.', $column, $model->table, $type),
                );
            }
        }
    }

    private function isUndersizedInteger(string $type): bool
    {
        $type = strtolower($type);

        return str_contains($type, 'int') && !str_contains($type, 'big');
    }
}
