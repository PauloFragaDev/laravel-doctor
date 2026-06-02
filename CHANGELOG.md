# Changelog

Todas las versiones notables de laravel-doctor.

## v0.1.0

Primera versión funcional.

### Análisis
- Motor estático sobre el AST de PHP (`nikic/php-parser`) y scanner propio de Blade.
- Inspección en runtime opcional (`--boot`) vía comando artisan `laravel-doctor:manifest`
  (rutas, config y modelos), con degradación a estático si la app no arranca.

### Reglas (18)
- **Seguridad:** `no-env-outside-config`, `no-mass-assignment-guarded-empty`,
  `no-raw-sql-interpolation`, `no-hardcoded-credentials`, `no-unescaped-blade-output`,
  `no-route-without-auth` (runtime), `no-debug-in-production` (runtime).
- **Performance / DB:** `prefer-exists-over-count`, `no-query-in-loop`, `no-all-then-filter`,
  `no-unindexed-foreign-key` (runtime).
- **Eloquent:** `no-save-in-loop-without-transaction`, `no-missing-casts-for-json` (runtime),
  `prefer-bigint-foreign-key` (runtime).
- **Arquitectura:** `no-fat-controller-method`, `prefer-form-request-validation`,
  `no-business-logic-in-route-closure`.
- **Blade:** `no-unescaped-blade-output`, `no-logic-in-blade`.

### Salidas e integración
- Reporters TTY, JSON (contrato estable para agentes) y GitHub (anotaciones inline).
- Comando `install` que instala una skill para agentes de IA.
- Comando `tui`: terminal interactiva para elegir proyecto y auditar.
- GitHub Action reutilizable (`action.yml`).

### Configuración
- `doctor.config.php` / `doctor.config.json`: desactivar reglas, forzar severidades, excluir rutas.
- Disables inline por comentario (`laravel-doctor-disable-line` / `-disable-next-line`).
