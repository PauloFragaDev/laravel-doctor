<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Runtime;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Runtime\ManifestRule;
use LaravelDoctor\Runtime\ManifestRuleContext;
use LaravelDoctor\Runtime\RuntimeManifest;

final class NoMissingCastsForJson implements ManifestRule
{
    private const JSON_TYPES = ['json', 'jsonb'];

    public function id(): string { return 'no-missing-casts-for-json'; }

    public function title(): string { return 'Columna JSON sin cast en el modelo'; }

    public function category(): string { return Categories::ELOQUENT; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Añade el cast "array" (o "json"/"object"/"collection") al atributo en el modelo; sin cast, la columna JSON se lee y escribe como string.';
    }

    public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void
    {
        foreach ($manifest->models as $model) {
            foreach ($model->columns as $column => $type) {
                if (!in_array(strtolower($type), self::JSON_TYPES, true)) {
                    continue;
                }
                if (in_array($column, $model->casts, true)) {
                    continue;
                }

                $context->report(
                    $model->class,
                    0,
                    sprintf('La columna JSON "%s" de %s no tiene cast: se leerá como string.', $column, $model->table),
                );
            }
        }
    }
}
