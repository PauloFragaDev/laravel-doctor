# laravel-doctor Boot-1 — Plan de implementación

> **For agentic workers:** ejecutar task por task con TDD (test → implementación → commit). Pasos con checkbox.

**Goal:** Arnés de runtime: extraer el manifiesto (rutas+config) vía artisan, cruzarlo con un track de reglas de manifiesto, y la regla `no-route-without-auth`, todo activado por `--boot` con degradación a estático.

**Tech Stack:** PHP 8.4, symfony/console; `illuminate/console` + `illuminate/support` en require-dev (para las clases del lado app). Trabajar desde `/var/www/html/laravel-doctor` (rama `feat/boot-1`).

Detalle de cada componente: ver `docs/superpowers/specs/2026-06-02-laravel-doctor-boot-1-design.md`.

---

- [ ] **Task 1: `RouteInfo` + `RuntimeManifest`** (mover desde `src/Engine/`, hacerlo clase concreta; actualizar `use` en `Engine`). Test: construye y expone datos; suite sigue verde.
- [ ] **Task 2: `ManifestParser`** — `parse(json): ?RuntimeManifest`. Test: JSON válido → manifiesto con rutas/config; JSON inválido/forma mala → null.
- [ ] **Task 3: `ManifestExtractor`** — runner inyectable. Test: runner fake éxito → parsea; runner fake exit≠0 → null.
- [ ] **Task 4: `ManifestRule` + `ManifestRuleContext` + `ManifestEngine`** — aislamiento por regla. Test: regla fake reporta; regla que lanza no rompe.
- [ ] **Task 5: `NoRouteWithoutAuth`** — POST/PUT/PATCH/DELETE sin auth dispara; GET sin auth no; POST con auth no.
- [ ] **Task 6: `ManifestRuleRegistry`** — fija el id.
- [ ] **Task 7: lado app** — `LaravelDoctorServiceProvider` + `ManifestCommand`; `illuminate/*` en require-dev; `extra.laravel.providers` en composer.json. (Integración-only; verificar que la suite carga sin errores.)
- [ ] **Task 8: `--boot` en `InspectCommand`** — extractor inyectable; con manifiesto suma diagnósticos runtime; sin él (null) avisa y sigue estático. Test con extractor fake (éxito y degradación).

Cada task: test que falla → implementación → test verde → `git commit` (Conventional Commits, sin emojis, sin Co-Authored-By).
