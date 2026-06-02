# laravel-doctor Boot-2 — Diseño (reglas runtime sobre config y modelos)

**Fecha:** 2026-06-02
**Estado:** Aprobado (modo autónomo)
**Depende de:** Boot-1 (arnés, RuntimeManifest, ManifestEngine, --boot).

## Resumen

Aprovecha el manifiesto de runtime para reglas precisas que el análisis estático no puede
hacer sin falsos positivos. Amplía el manifiesto con metadatos de modelos (casts + columnas
de la DB) y añade dos reglas runtime: una de config y una de modelos.

## Decisión de alcance (criterio de calidad)

De las 2 reglas Eloquent originalmente diferidas:
- **`no-missing-casts-for-json`**: se vuelve **precisa** con datos de runtime — la DB nos dice
  qué columnas son JSON y el modelo qué casts declara. Se incluye.
- **`no-nullable-relation-access`**: requiere correlacionar AST de la vista/controlador con
  metadatos de relación; sigue siendo propensa a falsos positivos. **Se mantiene diferida**
  (futuro), y en su lugar Boot-2 añade `no-debug-in-production`, de altísima señal y coste cero.

## Manifiesto ampliado

`RuntimeManifest` gana `models: ModelInfo[]`.
- `ModelInfo` (`final readonly`): `class` (string), `table` (string), `casts` (string[], las
  claves de cast del modelo), `columns` (array<string,string> nombre→tipo de la DB).

`ManifestCommand` (lado app) lo emite best-effort: descubre los modelos de `app/Models`,
instancia cada uno para leer `getTable()`/`getCasts()`, y obtiene las columnas con
`Schema::getColumns($table)` (Laravel 11+). Todo envuelto en try/catch: si no hay DB o falla,
ese modelo se omite (degradación; las reglas de modelo simplemente no disparan).

`ManifestParser` parsea la sección `models` (ausente → lista vacía).

## Reglas (ManifestRule)

**`no-debug-in-production`** — Seguridad, `error`
- Dispara si `config['app.env'] === 'production'` y `config['app.debug']` es verdadero.
- Locus: `file = 'config/app.php'`, `line = 0`.
- Impacto: APP_DEBUG en producción filtra stack traces, env y datos sensibles.

**`no-missing-casts-for-json`** — Eloquent, `warning`
- Por cada modelo, por cada `columns` de tipo `json`/`jsonb` cuyo nombre **no** está en `casts`
  → dispara.
- Locus: `file = $model->class`, `line = 0`.
- Impacto: una columna JSON sin cast se lee/escribe como string y rompe el acceso como array.
- Precisa y sin falsos positivos: usa el esquema real + los casts reales.

Ambas se registran en `ManifestRuleRegistry` (junto a `no-route-without-auth`).

## Testing

- `ManifestParser`: parsea `models` (con/ sin sección).
- `RuntimeManifest`/`ModelInfo`: value object.
- `no-debug-in-production`: production+debug→dispara; production+debug false→no; local+debug→no.
- `no-missing-casts-for-json`: columna json sin cast→dispara; con cast→no; columna no-json→no.
- `ManifestRuleRegistry`: fija los 3 ids.
- `ManifestCommand`: integración-only (no unit test).

## Roadmap posterior

- `no-nullable-relation-access` (AST + relaciones del manifiesto) y N+1 por observación real.
- **TUI** + README del repositorio.
