# laravel-doctor Plan 2A — Diseño (6 reglas estáticas adicionales)

**Fecha:** 2026-06-02
**Estado:** Aprobado para plan de implementación
**Depende de:** [v1 fase-1](2026-06-02-laravel-doctor-v1-design.md) (motor, contrato `Rule`, pipeline, score, reporters) — ya implementado y mergeado vía PR #1.

## Resumen

Segunda tanda de reglas del producto. Añade **6 reglas estáticas de alta señal** sobre la
API de reglas ya estable del v1, sin tocar motor, pipeline, score ni reporters. No introduce
arquitectura nueva.

Forma parte del roadmap: Plan 2A (este, reglas PHP) → Plan 2B (scanner Blade + 2 reglas
Blade) → fase boot (runtime).

## Alcance

**Incluido:** 6 reglas nuevas, cada una como clase que implementa el contrato `Rule`
existente, registrada en `RuleRegistry`, con fixtures (caso positivo + negativo).

**Explícitamente fuera (decisiones conscientes):**
- `no-missing-casts-for-json` y `no-nullable-relation-access`: **movidas a la fase boot**.
  Sin metadatos de runtime (qué columnas son JSON, qué relaciones son nullable) la detección
  estática generaría falsos positivos. En boot serán precisas.
- Reglas Blade (`no-unescaped-blade-output`, `no-logic-in-blade`) y el `BladeParser`: van en
  el Plan 2B.
- Config / supresiones / umbrales configurables: post-v1 (los umbrales son constantes internas).

## Reglas

Severidades y categorías usan los enums/constantes existentes (`Severity`, `Categories`).

### Seguridad

**`no-mass-assignment-guarded-empty`** — `error`
- Dispara con: una propiedad `$guarded = []` (array vacío) en una clase, o una llamada
  `unguard()` (`StaticCall` o `MethodCall` con nombre `unguard`, p. ej. `Model::unguard()`).
- AST: `PhpParser\Node\Stmt\Property` cuya primera `PropertyItem` se llama `guarded` y cuyo
  `default` es un `Node\Expr\Array_` vacío (`items === []`); o `StaticCall`/`MethodCall` con
  `name` (Identifier) `unguard`.
- Impacto: cualquier campo del request es asignable en masa.

**`no-raw-sql-interpolation`** — `error`
- Métodos vigilados: `whereRaw`, `havingRaw`, `orderByRaw`, `selectRaw`, `raw`, `statement`,
  `unprepared` (en `MethodCall` o `StaticCall`).
- Dispara si el **primer argumento** es una string interpolada
  (`Node\Scalar\InterpolatedString`) o una concatenación (`Node\Expr\BinaryOp\Concat`) que
  contiene al menos una `Node\Expr\Variable`.
- No dispara con string literal plana (`Node\Scalar\String_`).
- Impacto: inyección SQL.

### Performance

**`no-query-in-loop`** — `warning`
- Métodos de query: `get`, `first`, `find`, `count`, `pluck`, `paginate`, `sum`, `value`.
- Dispara: `MethodCall` con uno de esos nombres y con un nodo de bucle
  (`Foreach_`/`For_`/`While_`/`Do_`) entre sus ancestros (`$context->ancestors()`).
- Impacto: patrón N+1.

**`no-all-then-filter`** — `warning`
- Dispara: `MethodCall` cuyo `name` está en `filter`, `where`, `map`, `reject`, `sortBy`, y
  cuyo `var` es una llamada `all` (`MethodCall` o `StaticCall` con `name` `all`, p. ej.
  `Model::all()` o `$x->all()`).
- Impacto: trae toda la tabla a memoria y filtra en PHP.

### Arquitectura

**`no-fat-controller-method`** — `warning`
- Dispara: un `Node\Stmt\ClassMethod` contenido en una clase (`Node\Stmt\Class_` entre los
  ancestros) cuyo nombre **acaba en `Controller`**, cuando el cuerpo del método supera
  **40 líneas** (`getEndLine() - getStartLine() > 40`).
- Umbral: constante interna `MAX_METHOD_LINES = 40`.
- Impacto: controller con demasiada lógica; extraer a action/servicio.

**`no-business-logic-in-route-closure`** — `info`
- Dispara: un `Node\Expr\Closure` con **más de 5 sentencias** en su cuerpo
  (`count($node->stmts) > 5`), pasado a una ruta — detectado porque entre sus ancestros hay
  un `StaticCall` cuya clase (`Node\Name`) es `Route` y cuyo método está en
  `get`/`post`/`put`/`patch`/`delete`/`any`/`match`/`options` — **y solo** en archivos cuya
  ruta contiene `routes/` (`$context->filePath()`).
- Umbral: constante interna `MAX_CLOSURE_STMTS = 5`.
- Impacto: lógica de negocio en la definición de rutas; mover a un controller.

## Cambios en componentes existentes

- `src/Rules/RuleRegistry.php`: añadir las 6 instancias nuevas (pasa de 4 a 10 reglas).
- Nuevas clases en `src/Rules/{Security,Performance,Architecture}/`.
- No se modifica `Engine`, `Pipeline`, `ScoreCalculator`, reporters ni `RuleContext`.

## Manejo de errores

Sin cambios: el aislamiento por regla del `RuleVisitor` ya protege contra una regla que
lance excepción; un fallo en una regla nueva no afecta a las demás.

## Testing

- **Por regla:** fixture positivo (dispara) + negativo (no dispara). Para reglas con matices
  (interpolación vs literal, all()->filter() vs all() suelto, método largo vs corto, closure
  en `routes/` vs fuera) añadir el caso límite relevante.
- **`RuleRegistryTest`:** ampliar para fijar los 6 ids nuevos y que sigan siendo únicos.
- Suite completa debe seguir verde tras añadir las reglas.

## Roadmap posterior

- **Plan 2B:** `BladeScanner` propio (tokenizador ligero, preserva la línea exacta del
  `.blade.php`), ampliar `FileScanner` a `.blade.php`, y las 2 reglas Blade
  (`no-unescaped-blade-output`, `no-logic-in-blade`).
- **Fase boot:** `RuntimeManifest` real vía `php artisan` (rutas, modelos, config), N+1 real,
  rutas sin auth, y las 2 reglas Eloquent diferidas (`no-missing-casts-for-json`,
  `no-nullable-relation-access`).
