# laravel-doctor TUI — Diseño (terminal interactiva) + README

**Fecha:** 2026-06-02
**Estado:** Aprobado (modo autónomo)
**Depende de:** todo lo anterior (v1, 2A, 2B, Boot-1, Boot-2).

## Resumen

Terminal interactiva sobre el core: descubre proyectos Laravel en un directorio base, deja
elegir uno desde un menú y ejecutar acciones (auditar estático / auditar con --boot). Más el
README del repositorio, llamativo y explicativo.

## Decisiones

- **Sin dependencias nuevas**: se usa el `QuestionHelper`/`ChoiceQuestion` de symfony/console
  (ya presente). Nada de laravel/prompts.
- **Refactor DRY**: se extrae un servicio `Inspector` que encapsula scan → motores (PHP+Blade,
  +manifiesto si boot) → pipeline → score, devolviendo un `InspectionResult`. Lo usan tanto
  `InspectCommand` (refactor, comportamiento idéntico) como `TuiCommand`.

## Componentes

- `src/Analysis/InspectionResult.php` (`final readonly`): `score` (ScoreResult),
  `diagnostics` (Diagnostic[]), `bootFailed` (bool).
- `src/Analysis/Inspector.php`: `inspect(string $path, bool $boot): InspectionResult`.
  Recibe un `?ManifestExtractor` (inyectable para tests). Centraliza la lógica que hoy está en
  `InspectCommand`.
- `InspectCommand` (refactor): delega en `Inspector`; mantiene su constructor `?ManifestExtractor`
  (los tests existentes siguen válidos), el render, el aviso de degradación y el exit code.
- `src/Tui/ProjectInfo.php` (`final readonly`): `name`, `path`.
- `src/Tui/ProjectDiscovery.php`: `discover(string $baseDir): ProjectInfo[]`. Un directorio es
  un proyecto Laravel si contiene un fichero `artisan`. Ordenado por nombre. (Puro, testeable.)
- `src/Console/TuiCommand.php` (`tui`): opción `--base` (default cwd). Descubre proyectos; si
  no hay, avisa. Menú `ChoiceQuestion` para elegir proyecto (+ "Salir"); luego menú de acción
  (Auditar estático / Auditar con --boot / Volver). Ejecuta vía `Inspector`, renderiza con
  `TtyReporter`, y vuelve al menú hasta "Salir".
- `bin/laravel-doctor`: registra `TuiCommand`.

## Flujo

```
tui --base <dir>
  → ProjectDiscovery.discover → [proyectos]
  → elegir proyecto (o Salir)
  → elegir acción: estático | --boot | volver
  → Inspector.inspect → TtyReporter → imprime score + hallazgos
  → vuelve al menú de proyecto
```

## Manejo de errores

- Sin proyectos en el base → mensaje claro, exit 0.
- Entorno no interactivo (sin TTY) → el comando informa que requiere terminal interactiva y
  sale 0 (no se cuelga esperando input).

## Testing

- `ProjectDiscovery`: base con un proyecto (carpeta con `artisan`) y una sin él → descubre solo
  el primero; base vacío → [].
- `Inspector`: proyecto temporal con problema estático → result con diagnostics y score < 100;
  con extractor fake y boot=true → incluye hallazgos runtime; boot fallido → bootFailed=true.
- `InspectCommand`: los tests existentes siguen verdes tras el refactor.
- `TuiCommand`: `CommandTester::setInputs()` simula elegir proyecto → "Auditar estático" →
  "Salir"; se verifica que imprime el score y termina en 0; y caso "sin proyectos".

## README

`README.md` del repo: llamativo y coherente. Estructura: titular + pitch, badges, qué detecta
(las 4+ categorías y la lista de reglas), instalación, uso (CLI, --json para agentes, --boot,
TUI), ejemplo de salida, integración con agentes (skill), filosofía (determinista, sin DB en
estático), y roadmap. Tono claro, con ejemplos de comandos reales.
