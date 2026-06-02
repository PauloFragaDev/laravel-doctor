# laravel-doctor Boot-1 — Diseño (arnés de runtime)

**Fecha:** 2026-06-02
**Estado:** Aprobado (modo autónomo) para plan de implementación
**Depende de:** v1 + Plan 2A + Plan 2B.

## Resumen

Primer subsistema de la fase boot: un **arnés** que extrae un manifiesto de runtime de la
app Laravel del usuario (rutas + config) y lo cruza con reglas que lo consumen. Incluye una
regla exemplar (`no-route-without-auth`). Las reglas de modelos/Eloquent y N+1 van en Boot-2.

## Decisiones (brainstorming)

- **Extracción vía comando artisan propio**: la herramienta se instala en la app
  (`composer require --dev`), un `ServiceProvider` auto-descubierto registra
  `php artisan laravel-doctor:manifest`, que corre dentro del bootstrap real y emite JSON.
- **Activación opt-in con `--boot`**: por defecto sigue estático (seguro en CI). Con `--boot`
  intenta extraer; si falla (no bootea, sin `.env`), **avisa y cae a estático**.
- **Boot-1 entrega valor visible**: arnés + manifiesto (rutas+config) + regla
  `no-route-without-auth`.

## Arquitectura

```
--boot →  ManifestExtractor (php artisan laravel-doctor:manifest --json)
              → RuntimeManifest → ManifestEngine (ManifestRule[]) ─┐
.php → Engine (AST) ───────────────────────────────────────────── ┤→ Diagnostic[] → Pipeline → Score → Reporters
.blade.php → BladeEngine ────────────────────────────────────────┘
```

## Componentes

**Lado app (solo se cargan dentro de un Laravel real; Illuminate en `require-dev`):**
- `src/Laravel/LaravelDoctorServiceProvider.php` — declarado en `composer.json`
  (`extra.laravel.providers`); registra el comando.
- `src/Laravel/ManifestCommand.php` — `laravel-doctor:manifest`; recoge `Route::getRoutes()`
  y `config()`, imprime JSON `{ routes: [...], config: {...} }`. Fino, integración-only.

**Lado analizador (`src/Runtime/`):**
- `RouteInfo` (`final readonly`): `uri`, `methods` (string[]), `middleware` (string[]), `action`.
- `RuntimeManifest` (`final readonly`): `routes` (RouteInfo[]), `config` (array). **Sustituye**
  a la interfaz-marcador vacía del v1; se mueve de `src/Engine/` a `src/Runtime/` y se actualiza
  el `use` en `Engine` (la firma `Engine::inspect(?RuntimeManifest)` se mantiene como hueco).
- `ManifestParser` — `parse(string $json): ?RuntimeManifest`; `null` si el JSON o la forma no
  valen (puro, testeable con fixtures).
- `ManifestExtractor` — `extract(string $projectDir): ?RuntimeManifest`. Ejecuta el comando vía
  un *runner* inyectable (callable que devuelve `[int $exitCode, string $stdout]`; por defecto
  ejecuta `php artisan laravel-doctor:manifest --json` con `exec`). `null` ante fallo.
- `ManifestRule` (interfaz): `id/title/category/severity/recommendation` +
  `check(RuntimeManifest $manifest, ManifestRuleContext $context)`.
- `ManifestRuleContext` — `report(string $file, int $line, string $message)` arma el `Diagnostic`.
- `ManifestEngine` — `inspect(RuntimeManifest $manifest, ManifestRule[] $rules): Diagnostic[]`,
  con aislamiento por regla.
- `ManifestRuleRegistry` — `all(): ManifestRule[]` (las reglas runtime).

**Regla (`src/Rules/Runtime/`):**
- `NoRouteWithoutAuth` (Seguridad, `warning`): por cada ruta **que cambia estado**
  (método `POST`/`PUT`/`PATCH`/`DELETE`) cuyo `middleware` no contiene ninguno de
  `auth`, `auth:…`, `auth.basic`, `authenticate` → reporta (`file` = `routes`, `line` = 0,
  mensaje con método + uri). Restringir a métodos que mutan estado reduce el ruido de las
  páginas públicas GET.

## Flujo `--boot` y degradación

`InspectCommand` con `--boot`:
1. `ManifestExtractor::extract($path)`.
2. Si devuelve `RuntimeManifest`: corre `ManifestEngine` y suma sus `Diagnostic[]` a los de
   PHP/Blade.
3. Si devuelve `null`: imprime aviso "no se pudo bootear; analizando solo estático" y continúa
   con los diagnósticos estáticos. **Exit code y resto del flujo sin cambios.**
Sin `--boot`: comportamiento idéntico al actual (no se intenta extraer nada).

## Manejo de errores

- App no bootea / comando ausente / JSON inválido → `extract()`/`parse()` devuelven `null` →
  degradación a estático con aviso. Nunca rompe el run.
- Aislamiento por regla en `ManifestEngine`.

## Testing

- `ManifestParser`: fixtures JSON (válido → RuntimeManifest con rutas/config; inválido → null).
- `ManifestExtractor`: con *runner* fake → éxito (parsea) y fallo (exit≠0 → null).
- `NoRouteWithoutAuth`: ruta POST sin auth dispara; GET sin auth no; POST con `auth` no.
- `ManifestEngine`: corre reglas + aislamiento de excepción.
- `InspectCommand --boot`: con extractor inyectado/fake, mezcla diagnósticos runtime con
  estáticos; y degrada cuando el extractor da null.
- `ManifestCommand`/`ServiceProvider`: integración-only (no unit test; requieren Laravel real).

## Roadmap posterior

- **Boot-2:** modelos en el manifiesto (casts, relaciones), reglas Eloquent diferidas
  (`no-missing-casts-for-json`, `no-nullable-relation-access`) y N+1 preciso.
- **TUI:** terminal interactiva sobre el core + README del repositorio.
