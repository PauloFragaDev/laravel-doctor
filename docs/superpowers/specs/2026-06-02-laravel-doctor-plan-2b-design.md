# laravel-doctor Plan 2B — Diseño (track Blade + 2 reglas)

**Fecha:** 2026-06-02
**Estado:** Aprobado para plan de implementación
**Depende de:** v1 (motor, `Diagnostic`, `Pipeline`, `Score`, reporters) y Plan 2A — ambos en `main`.

## Resumen

Añade soporte para plantillas Blade mediante un **track paralelo** que convive con el motor
AST de PHP y converge en el mismo `Pipeline`. Incluye un scanner Blade propio (tokenizador
ligero que preserva la línea exacta del `.blade.php`), un contrato `BladeRule`, un
`BladeEngine`, y las 2 reglas Blade del set original del producto.

El motor PHP existente (`Engine`, `RuleVisitor`, reglas AST) **no se modifica**.

## Decisiones de diseño (tomadas en brainstorming)

- **Track Blade paralelo** (no forzar Blade en el contrato `Rule`/AST de PHP): contrato y
  motor propios para Blade, que emiten el mismo `Diagnostic`. Pipeline/Score/Reporters intactos.
- **Scanner propio** (no compilar con el BladeCompiler de Laravel): tokenizador por regex que
  preserva la línea exacta y no arrastra dependencias pesadas.
- **`no-logic-in-blade` por heurística de tokens** (no parsear el PHP interno con el AST):
  subcadenas sobre el texto que da el scanner; línea exacta, simple.

## Arquitectura

```
FileScanner  → .php ──────────────→ Engine (AST)         ─┐
             → .blade.php ────────→ BladeEngine (scanner) ─┴→ Diagnostic[] → Pipeline → Score → Reporters
```

## Componentes nuevos

Bajo `src/Blade/` (salvo las reglas, que van en `src/Rules/Blade/`):

- **`BladeConstruct`** (`final readonly`) — value object:
  - `kind`: enum `BladeConstructKind { RawEcho, EscapedEcho, PhpBlock }`.
  - `expression`: string con el texto interno (la expresión del echo o el cuerpo del `@php`).
  - `line`: int, línea (1-indexed) donde empieza la construcción en el `.blade.php`.
- **`BladeScanner`** — `scan(string $source): BladeConstruct[]`.
  - Quita primero los comentarios `{{-- … --}}`.
  - Extrae, con `preg_match_all` + `PREG_OFFSET_CAPTURE`:
    - `RawEcho`: `/\{!!(.+?)!!\}/s`
    - `EscapedEcho`: `/\{\{(.+?)\}\}/s` (tras quitar comentarios; no solapa con `{!! !!}`)
    - `PhpBlock`: `/@php\b(.*?)@endphp/s`
  - La línea se calcula como `substr_count(substr($source, 0, $offset), "\n") + 1`.
  - `expression` se guarda con `trim()`.
- **`BladeRule`** (interfaz) — `id()`, `title()`, `category()`, `severity()`,
  `recommendation()`, `enterConstruct(BladeConstruct $construct, BladeRuleContext $context)`.
- **`BladeRuleContext`** — construido por el `BladeEngine`; `report(BladeConstruct $c, string $message)`
  arma un `Diagnostic` con `file` (del engine), `line` (del construct) y los metadatos de la regla.
- **`BladeEngine`** — `inspect(SourceFile[] $bladeFiles): Diagnostic[]`. Por cada archivo:
  escanea y pasa cada construct a cada `BladeRule`, **aislando excepciones por regla** (try/catch,
  igual que `RuleVisitor`). Usa un `DiagnosticCollector`.
- **`BladeRuleRegistry`** — `all(): BladeRule[]` con las 2 reglas.

## Cambios en componentes existentes

- **`SourceType`** (enum nuevo, `src/Scanner/SourceType.php`): `Php`, `Blade`.
- **`SourceFile`**: añade `public SourceType $type` (tercer campo del constructor).
- **`FileScanner`**:
  - Recoge también `*.blade.php` (mantiene las exclusiones actuales).
  - Asigna `SourceType::Blade` si el path termina en `.blade.php`, `SourceType::Php` en otro
    caso. La comprobación de `.blade.php` va **antes** que la de `.php`.
- **`InspectCommand`**: particiona los `SourceFile` por `type`, corre `Engine` sobre los `Php`
  y `BladeEngine` sobre los `Blade`, concatena los `Diagnostic[]` y sigue igual
  (Pipeline → Score → reporter → exit codes).

## Reglas Blade

### `no-unescaped-blade-output` — Seguridad, `warning`
- Dispara: `BladeConstruct` de `kind` `RawEcho` cuya `expression` contiene una variable (`$`).
- No dispara: `EscapedEcho`, ni `RawEcho` sin variable (p. ej. `{!! 'literal' !!}`).
- `warning` (no `error`): el echo sin escapar es una feature legítima (HTML ya saneado); es
  señal a revisar, no fallo seguro.
- Impacto: salida sin escapar de una variable → XSS si el contenido viene del usuario.
- Recomendación: usar `{{ }}` (escapa) o sanear con un purificador antes del `{!! !!}`.

### `no-logic-in-blade` — Arquitectura, `warning`
- Dispara:
  1. Cualquier `BladeConstruct` de `kind` `PhpBlock`.
  2. Un `EscapedEcho` o `RawEcho` cuya `expression` contiene un token tipo query:
     `->get(`, `->all(`, `->first(`, `->where(`, `->count(`, `->save(`, `::all(`, `::where(`, `DB::`.
- Heurística por subcadena sobre `expression`; línea exacta.
- Impacto: consultas/lógica en la vista acoplan presentación y negocio.
- Recomendación: mover los datos/lógica al controller o a un view composer; pasar a la vista
  solo lo ya resuelto.

## Manejo de errores

El `BladeScanner` opera sobre texto (regex), no "rompe" como un parser: un `.blade.php` raro
simplemente produce menos constructs. El `BladeEngine` aísla excepciones por regla.

## Testing

- **`BladeScanner`:** tokenización de raw echo, escaped echo, `@php`; comentarios `{{-- --}}`
  ignorados; línea correcta en archivo multilínea.
- **Cada regla Blade:** fixture positivo + negativo.
- **`BladeEngine`:** aislamiento de excepción por regla.
- **`FileScanner`:** recoge `.blade.php` y asigna el `SourceType` correcto (y `.php` normal sigue
  siendo `Php`).
- **`BladeRuleRegistry`:** fija los 2 ids (`no-unescaped-blade-output`, `no-logic-in-blade`).
- **e2e:** muestra con un `.blade.php` problemático + un `.php` problemático → ambos en el mismo
  reporte y score.

## Roadmap posterior

- **Fase boot:** `RuntimeManifest` real vía `php artisan` (rutas, modelos, config), N+1 real,
  rutas sin auth, y las 2 reglas Eloquent diferidas (`no-missing-casts-for-json`,
  `no-nullable-relation-access`).
- **TUI:** terminal interactiva (selección de proyectos, menú de acciones) sobre el core.
