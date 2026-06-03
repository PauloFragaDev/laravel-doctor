<div align="center">

# 🩺 laravel-doctor

### Tu agente escribe Laravel a medias. Esto lo diagnostica.

**Auditor determinista para codebases Laravel** — seguridad, performance, Eloquent, arquitectura y Blade.
Sin magia, sin falsos positivos de relleno: análisis estático del AST + (opcional) inspección en runtime.

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%20·%2011%20·%2012-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![CI](https://github.com/PauloFragaDev/laravel-doctor/actions/workflows/ci.yml/badge.svg)](https://github.com/PauloFragaDev/laravel-doctor/actions/workflows/ci.yml)
[![Tests](https://img.shields.io/badge/tests-140%20passing-22c55e?style=flat)](#desarrollo)
[![License](https://img.shields.io/badge/license-MIT-000000?style=flat)](LICENSE)

</div>

---

## ¿Qué hace?

Escaneas tu proyecto y obtienes una **nota de 0 a 100** con los problemas priorizados, listos para que tú —o tu agente de IA— los arregléis:

```text
laravel-doctor — Score: 74/100 (Needs work)

[ERROR]   app/Http/Controllers/PayController.php:12  no-env-outside-config
    env() fuera de config/ devuelve null con la config cacheada en producción.
    → Mueve el valor a un archivo de config/ y léelo con config().

[WARNING] app/Models/User.php:8  no-mass-assignment-guarded-empty
    $guarded = [] deja todos los campos asignables en masa.
    → Define $fillable con los campos permitidos.

[WARNING] resources/views/show.blade.php:3  no-unescaped-blade-output
    Salida sin escapar de una variable ({!! !!}): XSS si el contenido viene del usuario.
    → Usa {{ }} (escapa solo) o sanea el HTML antes.
```

Cada hallazgo trae **dónde** está, **por qué importa** (impacto real, no jerga de linter) y **cómo** arreglarlo.

## ⚡ Inicio rápido

```bash
git clone https://github.com/PauloFragaDev/laravel-doctor
cd laravel-doctor && composer install

# 1) Crea el comando global (una sola vez, sin sudo):
ln -s "$(pwd)/bin/laravel-doctor" ~/.local/bin/laravel-doctor

# 2) Úsalo desde la carpeta que contiene tus apps:
cd /var/www/html
laravel-doctor          # abre el menú: elige proyecto y qué auditar
```

> El paso 1 enlaza el binario en `~/.local/bin` (que suele estar en el PATH). Si tras hacerlo
> el comando no aparece, abre una terminal nueva o ejecuta `hash -r`. Si `~/.local/bin` no
> existe o no está en tu PATH, créalo/añádelo:
> `mkdir -p ~/.local/bin && export PATH="$HOME/.local/bin:$PATH"`.

**Sin crear el comando global**, dentro del repo siempre puedes usar el binario por su ruta:

```bash
./bin/laravel-doctor                       # menú, usando el directorio actual como base
./bin/laravel-doctor tui --base /var/www/html
```

> `laravel-doctor` sin argumentos abre la **terminal interactiva** usando el directorio actual
> como base. Ejecútalo desde la carpeta que contiene tus apps (p. ej. `/var/www/html`) o desde
> dentro de una app Laravel concreta — el menú la detecta sola.
>
> Ojo: `composer install` en este repo **no** crea por sí solo un comando `laravel-doctor`
> (Composer solo enlaza binarios de las *dependencias*, no del propio paquete). Por eso el
> paso 1. Cuando el paquete esté en **Packagist**, bastará `composer global require
> laravel-doctor/laravel-doctor`.

## 🚀 Otros usos

```bash
# Auditar una ruta directamente (sin menú)
laravel-doctor inspect /var/www/html/mi-app

# Salida JSON estable (para CI o para tu agente de IA)
laravel-doctor inspect --json

# Análisis en runtime: arranca la app para auditar rutas y config reales
laravel-doctor inspect --boot

# Anotaciones inline para GitHub Actions
laravel-doctor inspect --github

# Menú apuntando a otra carpeta de proyectos
laravel-doctor tui --base /ruta/a/proyectos
```

Para usarlo **dentro de una app** (habilita `--boot`): `composer require --dev laravel-doctor/laravel-doctor`.

`inspect` devuelve **exit code 1** si hay algún hallazgo de severidad *error* — perfecto para fallar un pipeline de CI.

## 🔍 Qué detecta

Dos modos que se complementan:

- **Estático** (por defecto): analiza el AST de PHP (vía `nikic/php-parser`) y las plantillas Blade. Rápido, seguro, **no necesita DB ni `.env`** → ideal para CI.
- **Runtime** (`--boot`): arranca tu app vía un comando artisan propio para auditar **rutas, middleware y config reales**. Si la app no puede arrancar, avisa y cae a estático.

| Categoría | Reglas |
|-----------|--------|
| 🔒 **Seguridad** | `no-env-outside-config` · `no-mass-assignment-guarded-empty` · `no-raw-sql-interpolation` · `no-hardcoded-credentials` · `no-unescaped-blade-output` · `no-route-without-auth` ⚡ · `no-debug-in-production` ⚡ |
| 🚀 **Performance / DB** | `prefer-exists-over-count` · `no-query-in-loop` · `no-all-then-filter` · `no-unindexed-foreign-key` ⚡ |
| 🧬 **Eloquent** | `no-save-in-loop-without-transaction` · `no-missing-casts-for-json` ⚡ · `prefer-bigint-foreign-key` ⚡ |
| 🏗️ **Arquitectura** | `no-fat-controller-method` · `prefer-form-request-validation` · `no-business-logic-in-route-closure` |
| 🎨 **Blade** | `no-unescaped-blade-output` · `no-logic-in-blade` |

⚡ = requiere `--boot` (datos de runtime).

## 🤖 Integración con agentes de IA

El diferenciador: laravel-doctor no solo señala los problemas, **se los enseña a tu agente para que los arregle**.

```bash
./vendor/bin/laravel-doctor install
```

Instala una *skill* para Claude Code, Cursor, Codex y compañía. El bucle es:

> **laravel-doctor encuentra → tu agente lee el JSON → arregla con la recomendación → re-corres → la nota sube.**

El contrato JSON (`--json`) es estable: `{ score, label, diagnostics: [{ id, category, severity, file, line, message, recommendation }] }`.

## 🖥️ Terminal interactiva

`laravel-doctor` sin argumentos (o `laravel-doctor tui`) abre un **entorno full-screen** real
(construido con [php-tui](https://github.com/php-tui/php-tui)): paneles con bordes, navegación
por flechas, **búsqueda en vivo** y detalle del hallazgo a color.

```text
┌ 🩺 shop · Score 74/100 (Needs work) ──────────────────────────────────────┐
└───────────────────────────────────────────────────────────────────────────┘
┌ Hallazgos (12) ─────────────────────────┐┌ Detalle ───────────────────────┐
│ › ✖ no-env-outside-config app/Pay.php:12││ ✖ no-env-outside-config        │
│   ✖ no-raw-sql-interpolation Repo.php:8 ││                                │
│   ⚠ no-query-in-loop      app/List.php:3││ env() fuera de config/ devuelve│
│   ⚠ no-all-then-filter    app/User.php:5││ null con la config cacheada…   │
│                                         ││ → Mueve el valor a config()    │
└─────────────────────────────────────────┘└────────────────────────────────┘
┌ ↑↓ mover · / buscar · b runtime · Esc volver · q salir ───────────────────┐
└───────────────────────────────────────────────────────────────────────────┘
```

- **↑↓** navega, **Enter** abre el proyecto, **/** activa la **búsqueda en vivo** (filtra la
  lista al teclear), **b** activa/desactiva el análisis runtime (`--boot`), **Esc** vuelve,
  **q** sale.
- Paneles **proyectos** (inicio) y **hallazgos | detalle** (resultados), con score y badges de
  severidad a color, y rutas relativas.

Requiere una terminal interactiva (TTY); en CI/pipes usa `inspect`.

## ⚙️ Configuración

Opcional. Crea un `doctor.config.php` (o `doctor.config.json`) en la raíz para desactivar reglas, ajustar severidades o excluir rutas:

```php
<?php

return [
    'rules' => [
        'no-fat-controller-method' => false,   // desactivar
        'no-query-in-loop' => 'error',         // forzar severidad (info|warning|error)
    ],
    'exclude' => [
        'app/Legacy/*',                         // ignorar rutas (glob)
    ],
];
```

Sin archivo de config, el comportamiento es el de por defecto.

### Silenciar un hallazgo puntual

Con un comentario en el propio código (funciona en PHP y Blade):

```php
// laravel-doctor-disable-next-line no-env-outside-config
$key = env('STRIPE_KEY');

$key = env('STRIPE_KEY'); // laravel-doctor-disable-line
```

Sin nombrar regla, silencia todas las de esa línea; con el id, solo esa.

## 🔁 CI / GitHub Action

Hay una action reutilizable (`action.yml`) que audita cada PR y deja **anotaciones inline** donde el revisor ya mira:

```yaml
name: laravel-doctor
on: [pull_request]
permissions:
  contents: read
  pull-requests: write
jobs:
  audit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: PauloFragaDev/laravel-doctor@main
        # with:
        #   boot: 'true'   # análisis en runtime
```

El job falla (exit 1) si hay hallazgos de severidad *error*. También puedes invocarlo directo con `inspect --github` en cualquier CI.

## 🧠 Filosofía

- **Determinista**: mismas reglas, mismo resultado. Nada de "a veces lo pilla".
- **Honesto con los falsos positivos**: las heurísticas que no pueden ser exactas sin runtime se marcan `warning`, no `error`; las que necesitan datos de la DB esperan a `--boot`.
- **El impacto antes que la regla**: cada mensaje explica qué se rompe para tus usuarios.
- **Funciona donde trabajas**: CLI, CI (JSON + exit codes), agentes de IA y TUI.

## 🛠️ Desarrollo

```bash
composer install
vendor/bin/phpunit        # 109 tests
```

Arquitectura por capas, cada una testeable por separado: `Scanner` (descubre archivos) → motores (`Engine` AST · `BladeEngine` · `ManifestEngine` runtime) → `Pipeline` (dedup + orden) → `ScoreCalculator` → reporters (`Tty` · `Agent` JSON). Las reglas son clases declarativas; añadir una es una clase + su test de fixtures.

## 🗺️ Roadmap

- [x] Motor estático (PHP) + score + skill para agentes
- [x] Reglas de seguridad, performance, Eloquent y arquitectura
- [x] Soporte Blade
- [x] Inspección en runtime (`--boot`): rutas, config, modelos
- [x] Terminal interactiva (TUI)
- [x] Config `doctor.config.php` (activar/desactivar reglas, severidades, ignores)
- [x] GitHub Action con anotaciones en PR
- [x] Reglas runtime dirigidas por el esquema real (FK sin índice, FK no-bigint)
- [x] Detección de credenciales hardcodeadas
- [x] CI propia (matriz PHP 8.2–8.4)
- [ ] Publicación en Packagist

> Nota de diseño: el N+1 "por observación real" (ejecutar la app y contar queries) queda
> deliberadamente fuera: es no-determinista y contradice el principio de la herramienta.
> Preferimos reglas dirigidas por el esquema/manifiesto reales, precisas y reproducibles.

## Licencia

MIT.
