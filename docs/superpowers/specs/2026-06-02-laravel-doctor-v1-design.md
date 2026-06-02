# laravel-doctor — Diseño v1

**Fecha:** 2026-06-02
**Estado:** Aprobado para plan de implementación
**Inspirado en:** [millionco/react-doctor](https://github.com/millionco/react-doctor)

## Resumen

`laravel-doctor` es un auditor determinista para codebases Laravel, publicable
vía Composer, escrito en PHP nativo. Extrapola el enfoque de react-doctor (escaneo
determinista → hallazgos priorizados → nota global → skill que instala en tu agente
IA para que arregle) al ecosistema Laravel/PHP.

El v1 es un escáner **puramente estático** (no arranca la app), de modo que corre en
CI sin DB ni `.env`. La arquitectura deja preparado el hueco para la inspección en
runtime ("boot"), que entra como titular en v1.1.

## Objetivo y posicionamiento

- **Tipo:** producto serio, publicable (Composer / Packagist).
- **Público:** desarrolladores Laravel, que esperan tooling PHP (`composer require --dev`,
  `./vendor/bin/...`), como PHPStan/Larastan, Rector, Pint.
- **Diferenciador frente al prior art** (Enlightn, Larastan/PHPStan, Rector, Pint):
  el bucle score + skill para agentes IA, hueco poco explotado en Laravel.

## Alcance del v1

**Incluido:**
- CLI de auditoría con **score** (nota global del codebase).
- **Skill para agentes IA** (`install`): deja una skill en Claude Code/Cursor/Codex/etc.
  para que el agente lea los hallazgos y los corrija.
- ~14 reglas estáticas repartidas en 4 categorías: Seguridad, Performance/DB,
  Correctness Eloquent, Arquitectura.

**Explícitamente fuera del v1 (post-v1):**
- Inspección en runtime / boot de la app (arquitectada pero no implementada).
- Archivo de config (`doctor.config.php`), supresiones e ignores, disables inline.
- GitHub Action / comentarios en PR.
- Política de fallo configurable (`--fail-on`).
- Score vía API remota (el v1 calcula local).

## Decisiones de diseño

- **Runtime:** PHP nativo. Distribución Composer, ejecución `./vendor/bin/laravel-doctor`.
- **Análisis:** estático (AST) en v1; arquitectura preparada para estático + boot en v1.1.
- **Motor:** propio, sobre `nikic/php-parser` (base de PHPStan/Rector/Pint), con
  pipeline/score/skill propios — para poseer el DX y la "sensación react-doctor".
  (Descartado: plugin sobre PHPStan/Larastan, por acoplamiento y peor control del output.)
- **Score:** cálculo **local**, sin servicio externo (cero infra, privacidad, offline/CI).

## Arquitectura

```
Descubrimiento de archivos  →  Parseo AST (nikic/php-parser + parser Blade)
        ↓
Registro de reglas (visitors por nodo)  →  Diagnósticos crudos
        ↓
Pipeline de diagnósticos (severidad, ignores, dedup, surfaces)
        ↓
   ┌──────────────┴──────────────┐
Score (nota global)        Render TTY (hallazgos priorizados)
        ↓
Skill para agentes (install) ← los hallazgos alimentan al agente para arreglar
```

Cuatro límites bien definidos, testeables por separado:

- **`Scanner`** — descubre y lee archivos PHP/Blade, respeta `.gitignore`, filtra
  `vendor/`, tests, etc.
- **`Engine`** — parsea a AST y recorre los visitors de cada regla, emitiendo `Diagnostic`s.
  Acepta un `RuntimeManifest` opcional que en v1 siempre es `null` (hueco para boot).
- **`Pipeline`** — transforma/filtra diagnósticos (severidad por defecto, dedup, orden
  por prioridad).
- **`Reporters`** — consumen diagnósticos: TTY, score, y alimentación de la skill.

## Estructura de componentes (un solo paquete Composer, PSR-4 `LaravelDoctor\`)

```
laravel-doctor/
├── bin/laravel-doctor              # entrypoint CLI
├── src/
│   ├── Console/
│   │   ├── InspectCommand.php      # comando por defecto: escanea y reporta
│   │   └── InstallCommand.php      # instala la skill para el agente
│   ├── Scanner/
│   │   ├── FileScanner.php         # descubre archivos, respeta ignores
│   │   └── SourceFile.php          # value object: ruta + contenido + tipo (php|blade)
│   ├── Engine/
│   │   ├── Engine.php              # orquesta parse + recorrido de reglas
│   │   ├── PhpAstParser.php        # wrapper de nikic/php-parser
│   │   ├── BladeParser.php         # compila Blade y lo mapea a PHP+nodos
│   │   └── RuntimeManifest.php     # interfaz; en v1 siempre null (hueco para boot)
│   ├── Rules/
│   │   ├── Rule.php                # contrato base de una regla
│   │   ├── RuleRegistry.php        # registro + categorías
│   │   ├── Security/ ...
│   │   ├── Performance/ ...
│   │   ├── Eloquent/ ...
│   │   └── Architecture/ ...
│   ├── Diagnostics/
│   │   ├── Diagnostic.php          # value object del hallazgo
│   │   ├── Severity.php            # enum: error|warning|info
│   │   └── Pipeline.php            # filtros/transformaciones/dedup/orden
│   ├── Score/
│   │   └── ScoreCalculator.php     # nota global
│   └── Reporting/
│       ├── TtyReporter.php         # salida humana coloreada
│       └── AgentReporter.php       # salida estructurada (JSON) para la skill
├── skills/laravel-doctor/SKILL.md  # la skill que instala InstallCommand
└── tests/
```

Cada **regla** es una clase declarativa que implementa `Rule`: declara `id`, `title`,
`category`, `severity`, `recommendation`, y un método que registra los nodos AST que le
interesan (equivalente al `defineRule` + visitor de react-doctor, idiomático en PHP).
El `RuleRegistry` las agrupa por categoría.

**Punto de diseño clave:** cada regla trae un `message` orientado al **impacto real**
(no jerga de linter), porque ese texto es justo lo que leerá el agente para arreglar.

## Set de reglas del v1 (todas 100% estáticas)

**Seguridad**
- `no-mass-assignment-guarded-empty` — `$guarded = []` o `Model::unguard()` → asignación
  en masa sin restricción.
- `no-unescaped-blade-output` — `{!! $var !!}` con variable no constante → XSS.
- `no-env-outside-config` — `env()` fuera de `config/*.php` → `null` con config cacheada
  en producción.
- `no-raw-sql-interpolation` — `DB::raw()`, `whereRaw()`, `->statement()` con interpolación
  → inyección SQL.

**Performance / DB** (heurísticas estáticas; versiones runtime en v1.1)
- `no-query-in-loop` — query / `->get()` / `->first()` dentro de `foreach`/`for`/`while`
  → patrón N+1.
- `no-all-then-filter` — `Model::all()` seguido de `->filter()`/`->where()` en PHP → trae
  toda la tabla a memoria.
- `prefer-exists-over-count` — `->count() > 0` para comprobar existencia → usar `->exists()`.

**Correctness Eloquent**
- `no-missing-casts-for-json` — columna asignada con array/json sin `$casts` → lecturas
  devuelven string.
- `no-save-in-loop-without-transaction` — `->save()`/`->update()` en bucle sin
  `DB::transaction()` → escrituras sueltas, inconsistencia ante fallo.
- `no-nullable-relation-access` — acceso encadenado a relación posiblemente null sin
  `?->`/`optional()` → error en runtime.

**Arquitectura**
- `no-fat-controller-method` — método de controller que supera umbral de líneas o de
  dependencias inyectadas → mover a action/servicio.
- `no-logic-in-blade` — queries Eloquent o lógica de negocio en `.blade.php`.
- `prefer-form-request-validation` — `$request->validate([...])` inline en controller →
  extraer a Form Request.
- `no-business-logic-in-route-closure` — closure de ruta con más de N líneas → mover a
  controller.

**Notas:**
- Sin config en v1: todas las reglas arrancan **activas** con severidad por defecto fija.
- Umbrales (`N líneas`, `N dependencias`) son constantes internas sensatas, no configurables.
- Las heurísticas de N+1/null se marcan `warning` (no `error`) por posibles falsos
  positivos sin runtime — honestidad de producto.

## Score (local)

- Arranca en **100**. Cada diagnóstico resta una penalización ponderada por **severidad**
  (`error` > `warning`) y por **categoría** (seguridad > arquitectura).
- **Rendimientos decrecientes**: la penalización por regla se satura, de modo que 20
  hallazgos de la misma regla no hunden la nota a 0. Una nota baja = problemas variados
  y graves, no un patrón repetido.
- Devuelve `{ score: 0-100, label }` con etiquetas `Healthy / Needs work / At risk / Critical`.
- `ScoreCalculator` es **puro y testeable**: entrada lista de diagnósticos → salida número.
  Pesos como constantes. Migrar a API remota (estilo react-doctor) en v1.1 = cambiar el
  reporter, no el motor.

## Bucle de la skill (diferenciador)

`laravel-doctor install`:
1. Detecta el agente presente (Claude Code, Cursor, Codex…) por sus directorios conocidos.
2. Copia `skills/laravel-doctor/SKILL.md` al sitio adecuado de cada agente.
3. La skill instruye al agente a: ejecutar `laravel-doctor --json`, leer los diagnósticos
   estructurados (vía `AgentReporter`), y arreglar cada uno usando el `recommendation` +
   `message` de impacto de la regla.

**Contrato `AgentReporter` (JSON estable, versionado y testeado):**
```json
{
  "score": 0,
  "label": "string",
  "diagnostics": [
    { "id": "string", "category": "string", "severity": "error|warning|info",
      "file": "string", "line": 0, "message": "string", "recommendation": "string" }
  ]
}
```

**Bucle de valor completo del v1:** `laravel-doctor` encuentra → muestra score → el agente
lee el JSON → arregla con la recomendación → re-corres → la nota sube.

## Flujo de datos (run típico)

1. `InspectCommand` resuelve raíz y opciones (`--json`, `--no-color`).
2. `FileScanner` descubre `*.php` y `*.blade.php`; descarta `vendor/`, `node_modules/`,
   `storage/`, tests, y lo ignorado por `.gitignore`.
3. `Engine` parsea cada archivo y recorre los visitors registrados; recoge `Diagnostic`s.
   `RuntimeManifest = null`.
4. `Pipeline` aplica severidad por defecto, dedup (misma regla+archivo+línea), y ordena por
   prioridad (categoría × severidad).
5. Bifurca: `ScoreCalculator` produce la nota; `TtyReporter` o `AgentReporter` renderizan
   según `--json`.
6. **Exit code:** `0` si no hay `error`s; `1` si hay al menos uno. (Política de fallo
   configurable → post-v1.)

## Manejo de errores

- **Archivo que no parsea:** se captura, se emite `Diagnostic` informativo y se **continúa**.
  Un archivo roto nunca tumba el run.
- **Regla que lanza excepción:** aislada por regla (try/catch por visitor); se registra en
  `--verbose` y se sigue. Motor resiliente regla a regla.
- **Sin archivos Laravel detectados:** mensaje claro y exit `0`.
- **Blade que no compila:** degrada a texto/PHP plano y avisa, en vez de fallar.

## Testing

- **Por regla:** cada regla con fixtures positivo (dispara) y negativo (no dispara). Grueso
  de la suite; da confianza para añadir reglas sin regresiones.
- **Pipeline y score:** tests puros entrada→salida.
- **Reporters:** snapshot del JSON del `AgentReporter` (contrato con la skill) y del render TTY.
- **End-to-end:** mini-proyecto Laravel de fixture en `tests/`; corre el CLI completo y verifica
  score + exit code.
- **Sin tests de boot en v1** (no hay boot todavía).

## Roadmap posterior (fuera del v1)

- **v1.1:** arnés de boot (`RuntimeManifest` real vía `php artisan`: rutas, modelos, config,
  middleware) + reglas runtime, con N+1 "de verdad" y rutas sin auth como titulares.
- Config `doctor.config.php`, supresiones e ignores, disables inline.
- GitHub Action con anotaciones/comentarios en PR.
- Versión Vue como producto hermano (ciclo spec → plan propio).
