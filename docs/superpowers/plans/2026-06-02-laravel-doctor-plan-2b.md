# laravel-doctor Plan 2B — Implementación (track Blade + 2 reglas)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir un track Blade paralelo (scanner propio + contrato `BladeRule` + `BladeEngine`) que emite los mismos `Diagnostic` que el motor PHP, más las 2 reglas Blade, sin tocar el motor AST existente.

**Architecture:** `FileScanner` etiqueta cada archivo con un `SourceType` (`Php`/`Blade`). `InspectCommand` enruta los `.php` al `Engine` (AST) y los `.blade.php` al `BladeEngine`, que tokeniza con `BladeScanner` y corre las `BladeRule`. Ambos flujos concatenan sus `Diagnostic[]` y siguen por el `Pipeline`/`Score`/reporters ya existentes.

**Tech Stack:** PHP 8.4, nikic/php-parser ^5 (solo el track PHP), PHPUnit ^11. Trabajar desde `/var/www/html/laravel-doctor` (rama `feat/plan-2b`).

**Convenciones fijas:**
- Value objects `final readonly`. Namespace `LaravelDoctor\`.
- Diagnósticos reutilizan `Diagnostic`, `Severity`, `Categories`, `DiagnosticCollector` del v1.
- Tests de reglas Blade usan un helper **`analyze()`** que pasa por el `BladeEngine` (NO `run()`).
- El scanner enmascara comentarios `{{-- --}}` reemplazando cada carácter no-`\n` por un espacio, para **preservar offsets y números de línea** (verificado).

---

### Task 1: `SourceType` y campo `type` en `SourceFile`

**Files:**
- Create: `src/Scanner/SourceType.php`
- Modify: `src/Scanner/SourceFile.php`
- Test: `tests/Scanner/SourceFileTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Scanner/SourceFileTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Scanner;

use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class SourceFileTest extends TestCase
{
    public function test_defaults_to_php_type(): void
    {
        $f = new SourceFile('app/X.php', '<?php');
        $this->assertSame(SourceType::Php, $f->type);
    }

    public function test_accepts_blade_type(): void
    {
        $f = new SourceFile('resources/views/x.blade.php', '<div></div>', SourceType::Blade);
        $this->assertSame(SourceType::Blade, $f->type);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter SourceFileTest`
Expected: FAIL — `SourceType` no existe.

- [ ] **Step 3: Crear `SourceType`**

`src/Scanner/SourceType.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Scanner;

enum SourceType
{
    case Php;
    case Blade;
}
```

- [ ] **Step 4: Modificar `SourceFile`**

Reemplaza `src/Scanner/SourceFile.php` por:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Scanner;

final readonly class SourceFile
{
    public function __construct(
        public string $path,
        public string $contents,
        public SourceType $type = SourceType::Php,
    ) {
    }
}
```

(El valor por defecto `SourceType::Php` mantiene válidas todas las construcciones de 2
argumentos que ya existen en los tests de reglas PHP.)

- [ ] **Step 5: Correr el test y la suite**

Run: `vendor/bin/phpunit --filter SourceFileTest`
Expected: PASS (2 tests).
Run: `vendor/bin/phpunit`
Expected: toda la suite sigue verde (el default no rompe nada).

- [ ] **Step 6: Commit**

```bash
git add src/Scanner/SourceType.php src/Scanner/SourceFile.php tests/Scanner/SourceFileTest.php
git commit -m "feat: SourceType y campo type en SourceFile"
```

---

### Task 2: `FileScanner` recoge `.blade.php` y etiqueta el tipo

**Files:**
- Modify: `src/Scanner/FileScanner.php`
- Modify: `tests/Scanner/FileScannerTest.php`

- [ ] **Step 1: Añadir el test que falla**

Añade estos dos métodos a la clase `FileScannerTest` (en `tests/Scanner/FileScannerTest.php`),
y en `setUp()` crea además un archivo Blade:

En `setUp()`, tras las líneas que crean los ficheros, añade:
```php
        mkdir($this->root . '/resources/views', 0777, true);
        file_put_contents($this->root . '/resources/views/home.blade.php', "<div></div>\n");
```

Nuevos métodos de test:
```php
    public function test_collects_blade_files_tagged_as_blade(): void
    {
        $files = (new \LaravelDoctor\Scanner\FileScanner())->scan($this->root);
        $blade = array_values(array_filter($files, fn ($f) => str_ends_with($f->path, '.blade.php')));

        $this->assertCount(1, $blade);
        $this->assertSame(\LaravelDoctor\Scanner\SourceType::Blade, $blade[0]->type);
    }

    public function test_plain_php_tagged_as_php(): void
    {
        $files = (new \LaravelDoctor\Scanner\FileScanner())->scan($this->root);
        $user = array_values(array_filter($files, fn ($f) => str_ends_with($f->path, 'User.php')))[0];

        $this->assertSame(\LaravelDoctor\Scanner\SourceType::Php, $user->type);
    }
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter FileScannerTest`
Expected: FAIL — `$blade[0]->type` no es `Blade` (hoy no se asigna tipo).

- [ ] **Step 3: Modificar `FileScanner`**

En `src/Scanner/FileScanner.php`, dentro de `scan()`, donde se construye el `SourceFile`,
reemplaza:
```php
            $files[] = new SourceFile($path, $contents);
```
por:
```php
            $type = str_ends_with($path, '.blade.php') ? SourceType::Blade : SourceType::Php;
            $files[] = new SourceFile($path, $contents, $type);
```

Y añade el import al inicio del archivo (junto al `use ... SourceFile;`):
```php
use LaravelDoctor\Scanner\SourceType;
```

(Nota: los `.blade.php` ya se recogían porque terminan en `.php`; este cambio solo les asigna
el tipo correcto. La comprobación de `.blade.php` va antes que la de `.php` por construcción.)

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter FileScannerTest`
Expected: PASS (todos los métodos de FileScannerTest).

- [ ] **Step 5: Commit**

```bash
git add src/Scanner/FileScanner.php tests/Scanner/FileScannerTest.php
git commit -m "feat: FileScanner recoge .blade.php y etiqueta SourceType"
```

---

### Task 3: `BladeConstruct` y `BladeConstructKind`

**Files:**
- Create: `src/Blade/BladeConstructKind.php`
- Create: `src/Blade/BladeConstruct.php`
- Test: `tests/Blade/BladeConstructTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Blade/BladeConstructTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use PHPUnit\Framework\TestCase;

final class BladeConstructTest extends TestCase
{
    public function test_holds_its_data(): void
    {
        $c = new BladeConstruct(BladeConstructKind::RawEcho, '$html', 4);
        $this->assertSame(BladeConstructKind::RawEcho, $c->kind);
        $this->assertSame('$html', $c->expression);
        $this->assertSame(4, $c->line);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter BladeConstructTest`
Expected: FAIL — clases no existen.

- [ ] **Step 3: Crear `BladeConstructKind`**

`src/Blade/BladeConstructKind.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

enum BladeConstructKind
{
    case RawEcho;       // {!! ... !!}
    case EscapedEcho;   // {{ ... }}
    case PhpBlock;      // @php ... @endphp
}
```

- [ ] **Step 4: Crear `BladeConstruct`**

`src/Blade/BladeConstruct.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

final readonly class BladeConstruct
{
    public function __construct(
        public BladeConstructKind $kind,
        public string $expression,
        public int $line,
    ) {
    }
}
```

- [ ] **Step 5: Correr el test**

Run: `vendor/bin/phpunit --filter BladeConstructTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add src/Blade/BladeConstructKind.php src/Blade/BladeConstruct.php tests/Blade/BladeConstructTest.php
git commit -m "feat: value objects BladeConstruct y BladeConstructKind"
```

---

### Task 4: `BladeScanner`

**Files:**
- Create: `src/Blade/BladeScanner.php`
- Test: `tests/Blade/BladeScannerTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Blade/BladeScannerTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeConstructKind;
use LaravelDoctor\Blade\BladeScanner;
use PHPUnit\Framework\TestCase;

final class BladeScannerTest extends TestCase
{
    private const TEMPLATE = "<div>\n  {{-- un comentario\n  multilinea --}}\n  {!! \$html !!}\n  {{ \$name }}\n  @php\n    \$x = 1;\n  @endphp\n</div>\n";

    public function test_extracts_each_construct_with_correct_line(): void
    {
        $constructs = (new BladeScanner())->scan(self::TEMPLATE);

        $byKind = [];
        foreach ($constructs as $c) {
            $byKind[$c->kind->name][] = $c;
        }

        $this->assertCount(1, $byKind['RawEcho']);
        $this->assertSame('$html', $byKind['RawEcho'][0]->expression);
        $this->assertSame(4, $byKind['RawEcho'][0]->line);

        $this->assertCount(1, $byKind['EscapedEcho']);
        $this->assertSame('$name', $byKind['EscapedEcho'][0]->expression);
        $this->assertSame(5, $byKind['EscapedEcho'][0]->line);

        $this->assertCount(1, $byKind['PhpBlock']);
        $this->assertSame('$x = 1;', $byKind['PhpBlock'][0]->expression);
        $this->assertSame(6, $byKind['PhpBlock'][0]->line);
    }

    public function test_ignores_comments(): void
    {
        // El comentario contiene texto que NO debe convertirse en constructs.
        $constructs = (new BladeScanner())->scan("{{-- {{ \$x }} --}}\n");
        $this->assertSame([], $constructs);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter BladeScannerTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `BladeScanner`**

`src/Blade/BladeScanner.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

final class BladeScanner
{
    private const PATTERNS = [
        [BladeConstructKind::RawEcho, '/\{!!(.+?)!!\}/s'],
        [BladeConstructKind::EscapedEcho, '/\{\{(.+?)\}\}/s'],
        [BladeConstructKind::PhpBlock, '/@php\b(.*?)@endphp/s'],
    ];

    /**
     * @return BladeConstruct[]
     */
    public function scan(string $source): array
    {
        // Enmascara los comentarios {{-- --}} reemplazando cada carácter no-\n por un
        // espacio: así los offsets y los números de línea del resto se preservan.
        $masked = preg_replace_callback(
            '/\{\{--.*?--\}\}/s',
            static fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]),
            $source,
        );

        $constructs = [];
        foreach (self::PATTERNS as [$kind, $pattern]) {
            if (preg_match_all($pattern, $masked, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $offset = $match[0][1];
                    $line = substr_count(substr($masked, 0, $offset), "\n") + 1;
                    $constructs[] = new BladeConstruct($kind, trim($match[1][0]), $line);
                }
            }
        }

        return $constructs;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter BladeScannerTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Blade/BladeScanner.php tests/Blade/BladeScannerTest.php
git commit -m "feat: BladeScanner tokeniza .blade.php preservando la línea"
```

---

### Task 5: Contrato `BladeRule` y `BladeRuleContext`

**Files:**
- Create: `src/Blade/BladeRule.php`
- Create: `src/Blade/BladeRuleContext.php`
- Test: `tests/Blade/BladeRuleContextTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Blade/BladeRuleContextTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class BladeRuleContextTest extends TestCase
{
    private function fakeRule(): BladeRule
    {
        return new class implements BladeRule {
            public function id(): string { return 'fake-blade-rule'; }
            public function title(): string { return 'Fake'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'arregla esto'; }
            public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void {}
        };
    }

    public function test_report_builds_diagnostic(): void
    {
        $collector = new DiagnosticCollector();
        $ctx = new BladeRuleContext($this->fakeRule(), 'resources/views/x.blade.php', $collector);

        $ctx->report(new BladeConstruct(BladeConstructKind::RawEcho, '$html', 9), 'mensaje');

        $all = $collector->all();
        $this->assertCount(1, $all);
        $this->assertSame('fake-blade-rule', $all[0]->ruleId);
        $this->assertSame('security', $all[0]->category);
        $this->assertSame(Severity::Warning, $all[0]->severity);
        $this->assertSame('resources/views/x.blade.php', $all[0]->file);
        $this->assertSame(9, $all[0]->line);
        $this->assertSame('mensaje', $all[0]->message);
        $this->assertSame('arregla esto', $all[0]->recommendation);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter BladeRuleContextTest`
Expected: FAIL — interfaces/clases no existen.

- [ ] **Step 3: Crear `BladeRule`**

`src/Blade/BladeRule.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Diagnostics\Severity;

interface BladeRule
{
    public function id(): string;

    public function title(): string;

    /** Una de las constantes de LaravelDoctor\Diagnostics\Categories. */
    public function category(): string;

    public function severity(): Severity;

    public function recommendation(): string;

    public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void;
}
```

- [ ] **Step 4: Crear `BladeRuleContext`**

`src/Blade/BladeRuleContext.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;

final class BladeRuleContext
{
    public function __construct(
        private BladeRule $rule,
        private string $file,
        private DiagnosticCollector $collector,
    ) {
    }

    public function report(BladeConstruct $construct, string $message): void
    {
        $this->collector->add(new Diagnostic(
            ruleId: $this->rule->id(),
            category: $this->rule->category(),
            severity: $this->rule->severity(),
            file: $this->file,
            line: $construct->line,
            message: $message,
            recommendation: $this->rule->recommendation(),
        ));
    }
}
```

- [ ] **Step 5: Correr el test**

Run: `vendor/bin/phpunit --filter BladeRuleContextTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add src/Blade/BladeRule.php src/Blade/BladeRuleContext.php tests/Blade/BladeRuleContextTest.php
git commit -m "feat: contrato BladeRule y BladeRuleContext"
```

---

### Task 6: `BladeEngine`

**Files:**
- Create: `src/Blade/BladeEngine.php`
- Test: `tests/Blade/BladeEngineTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Blade/BladeEngineTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class BladeEngineTest extends TestCase
{
    private function rawEchoReporter(): BladeRule
    {
        return new class implements BladeRule {
            public function id(): string { return 'reports-raw'; }
            public function title(): string { return 'raw'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'fix'; }
            public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
            {
                if ($construct->kind === \LaravelDoctor\Blade\BladeConstructKind::RawEcho) {
                    $context->report($construct, 'raw echo');
                }
            }
        };
    }

    public function test_runs_blade_rules_over_files(): void
    {
        $engine = new BladeEngine([$this->rawEchoReporter()]);
        $d = $engine->inspect([
            new SourceFile('resources/views/x.blade.php', "{!! \$a !!}\n{{ \$b }}", SourceType::Blade),
        ]);

        $this->assertCount(1, $d);
        $this->assertSame('reports-raw', $d[0]->ruleId);
        $this->assertSame('resources/views/x.blade.php', $d[0]->file);
    }

    public function test_isolates_rule_exceptions(): void
    {
        $boom = new class implements BladeRule {
            public function id(): string { return 'boom'; }
            public function title(): string { return 'boom'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'x'; }
            public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
            {
                throw new \RuntimeException('regla rota');
            }
        };

        $engine = new BladeEngine([$boom]);
        $d = $engine->inspect([new SourceFile('x.blade.php', "{!! \$a !!}", SourceType::Blade)]);

        $this->assertSame([], $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter BladeEngineTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `BladeEngine`**

`src/Blade/BladeEngine.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Scanner\SourceFile;

final class BladeEngine
{
    private BladeScanner $scanner;

    /**
     * @param BladeRule[] $rules
     */
    public function __construct(private array $rules)
    {
        $this->scanner = new BladeScanner();
    }

    /**
     * @param SourceFile[] $files
     * @return Diagnostic[]
     */
    public function inspect(array $files): array
    {
        $collector = new DiagnosticCollector();

        foreach ($files as $file) {
            $contexts = [];
            foreach ($this->rules as $rule) {
                $contexts[$rule->id()] = new BladeRuleContext($rule, $file->path, $collector);
            }

            foreach ($this->scanner->scan($file->contents) as $construct) {
                foreach ($this->rules as $rule) {
                    try {
                        $rule->enterConstruct($construct, $contexts[$rule->id()]);
                    } catch (\Throwable) {
                        // Aislamiento por regla: una regla rota no tumba el run.
                    }
                }
            }
        }

        return $collector->all();
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter BladeEngineTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Blade/BladeEngine.php tests/Blade/BladeEngineTest.php
git commit -m "feat: BladeEngine con aislamiento por regla"
```

---

### Task 7: Regla `no-unescaped-blade-output`

**Files:**
- Create: `src/Rules/Blade/NoUnescapedBladeOutput.php`
- Test: `tests/Rules/Blade/NoUnescapedBladeOutputTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Blade/NoUnescapedBladeOutputTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Blade;

use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Rules\Blade\NoUnescapedBladeOutput;
use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class NoUnescapedBladeOutputTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new BladeEngine([new NoUnescapedBladeOutput()]))
            ->inspect([new SourceFile('resources/views/x.blade.php', $code, SourceType::Blade)]);
    }

    public function test_flags_raw_echo_with_variable(): void
    {
        $d = $this->analyze("<p>{!! \$content !!}</p>");
        $this->assertCount(1, $d);
        $this->assertSame('no-unescaped-blade-output', $d[0]->ruleId);
    }

    public function test_does_not_flag_escaped_echo(): void
    {
        $d = $this->analyze("<p>{{ \$content }}</p>");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_raw_echo_without_variable(): void
    {
        $d = $this->analyze("<p>{!! '<br>' !!}</p>");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoUnescapedBladeOutputTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Blade/NoUnescapedBladeOutput.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;

final class NoUnescapedBladeOutput implements BladeRule
{
    public function id(): string { return 'no-unescaped-blade-output'; }

    public function title(): string { return 'Salida Blade sin escapar'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Usa {{ }} (escapa solo) en vez de {!! !!}, o sanea el HTML con un purificador antes de imprimirlo sin escapar.';
    }

    public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
    {
        if ($construct->kind !== BladeConstructKind::RawEcho) {
            return;
        }
        if (!str_contains($construct->expression, '$')) {
            return;
        }

        $context->report($construct, 'Salida sin escapar de una variable ({!! !!}): XSS si el contenido viene del usuario.');
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoUnescapedBladeOutputTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Blade/NoUnescapedBladeOutput.php tests/Rules/Blade/NoUnescapedBladeOutputTest.php
git commit -m "feat(rules): no-unescaped-blade-output"
```

---

### Task 8: Regla `no-logic-in-blade`

**Files:**
- Create: `src/Rules/Blade/NoLogicInBlade.php`
- Test: `tests/Rules/Blade/NoLogicInBladeTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Blade/NoLogicInBladeTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Blade;

use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Rules\Blade\NoLogicInBlade;
use LaravelDoctor\Scanner\SourceFile;
use LaravelDoctor\Scanner\SourceType;
use PHPUnit\Framework\TestCase;

final class NoLogicInBladeTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new BladeEngine([new NoLogicInBlade()]))
            ->inspect([new SourceFile('resources/views/x.blade.php', $code, SourceType::Blade)]);
    }

    public function test_flags_php_block(): void
    {
        $d = $this->analyze("@php\n\$x = 1;\n@endphp");
        $this->assertCount(1, $d);
        $this->assertSame('no-logic-in-blade', $d[0]->ruleId);
    }

    public function test_flags_query_in_echo(): void
    {
        $d = $this->analyze("<ul>{{ User::all() }}</ul>");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_plain_echo(): void
    {
        $d = $this->analyze("<p>{{ \$user->name }}</p>");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoLogicInBladeTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Blade/NoLogicInBlade.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;

final class NoLogicInBlade implements BladeRule
{
    private const QUERY_TOKENS = [
        '->get(', '->all(', '->first(', '->where(', '->count(', '->save(', '::all(', '::where(', 'DB::',
    ];

    public function id(): string { return 'no-logic-in-blade'; }

    public function title(): string { return 'Lógica o consultas en la plantilla Blade'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Mueve los datos y la lógica al controller o a un view composer; la vista debería recibir solo lo ya resuelto.';
    }

    public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
    {
        if ($construct->kind === BladeConstructKind::PhpBlock) {
            $context->report($construct, 'Bloque @php en la vista: la lógica no debería vivir en la plantilla.');

            return;
        }

        foreach (self::QUERY_TOKENS as $token) {
            if (str_contains($construct->expression, $token)) {
                $context->report($construct, 'Consulta a la base de datos dentro de la vista: acopla presentación y negocio.');

                return;
            }
        }
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoLogicInBladeTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Blade/NoLogicInBlade.php tests/Rules/Blade/NoLogicInBladeTest.php
git commit -m "feat(rules): no-logic-in-blade"
```

---

### Task 9: `BladeRuleRegistry`

**Files:**
- Create: `src/Blade/BladeRuleRegistry.php`
- Test: `tests/Blade/BladeRuleRegistryTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Blade/BladeRuleRegistryTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Blade;

use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleRegistry;
use PHPUnit\Framework\TestCase;

final class BladeRuleRegistryTest extends TestCase
{
    public function test_returns_blade_rules_with_unique_ids(): void
    {
        $rules = BladeRuleRegistry::all();

        $this->assertContainsOnlyInstancesOf(BladeRule::class, $rules);
        $this->assertCount(2, $rules);

        $ids = array_map(fn (BladeRule $r) => $r->id(), $rules);
        $this->assertSame($ids, array_unique($ids));
        $this->assertContains('no-unescaped-blade-output', $ids);
        $this->assertContains('no-logic-in-blade', $ids);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter BladeRuleRegistryTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `BladeRuleRegistry`**

`src/Blade/BladeRuleRegistry.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

use LaravelDoctor\Rules\Blade\NoLogicInBlade;
use LaravelDoctor\Rules\Blade\NoUnescapedBladeOutput;

final class BladeRuleRegistry
{
    /**
     * @return BladeRule[]
     */
    public static function all(): array
    {
        return [
            new NoUnescapedBladeOutput(),
            new NoLogicInBlade(),
        ];
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter BladeRuleRegistryTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add src/Blade/BladeRuleRegistry.php tests/Blade/BladeRuleRegistryTest.php
git commit -m "feat: BladeRuleRegistry con las 2 reglas blade"
```

---

### Task 10: Cablear el track Blade en `InspectCommand` + e2e

**Files:**
- Modify: `src/Console/InspectCommand.php`
- Test: `tests/Console/InspectBladeTest.php`

- [ ] **Step 1: Escribir el test e2e que falla**

`tests/Console/InspectBladeTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InspectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InspectBladeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-blade-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        mkdir($this->root . '/resources/views', 0777, true);
        // Problema PHP (error) + problema Blade (warning) en el mismo proyecto.
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
        file_put_contents($this->root . '/resources/views/show.blade.php', "<p>{!! \$html !!}</p>");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_reports_php_and_blade_findings_together(): void
    {
        $tester = new CommandTester(new InspectCommand());
        $exit = $tester->execute(['path' => $this->root, '--json' => true]);

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_map(fn ($d) => $d['id'], $data['diagnostics']);

        $this->assertContains('no-env-outside-config', $ids);        // del motor PHP
        $this->assertContains('no-unescaped-blade-output', $ids);    // del motor Blade
        $this->assertSame(1, $exit);                                 // hay un error PHP
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter InspectBladeTest`
Expected: FAIL — el `.blade.php` aún no se procesa, falta `no-unescaped-blade-output`.

- [ ] **Step 3: Modificar `InspectCommand`**

En `src/Console/InspectCommand.php`, añade los imports (junto a los demás `use`):
```php
use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Blade\BladeRuleRegistry;
use LaravelDoctor\Scanner\SourceType;
```

Y reemplaza, dentro de `execute()`, estas dos líneas:
```php
        $files = (new FileScanner())->scan($path);
        $diagnostics = (new Engine(RuleRegistry::all()))->inspect($files);
```
por:
```php
        $files = (new FileScanner())->scan($path);

        $phpFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Php));
        $bladeFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Blade));

        $diagnostics = array_merge(
            (new Engine(RuleRegistry::all()))->inspect($phpFiles),
            (new BladeEngine(BladeRuleRegistry::all()))->inspect($bladeFiles),
        );
```

- [ ] **Step 4: Correr el test y la suite completa**

Run: `vendor/bin/phpunit --filter InspectBladeTest`
Expected: PASS (1 test).
Run: `vendor/bin/phpunit`
Expected: toda la suite verde.

- [ ] **Step 5: Verificación manual del binario**

Run:
```bash
cd /tmp && rm -rf ld-2b && mkdir -p ld-2b/resources/views && printf "<p>{!! \$html !!}</p>\n@php \$x = User::all(); @endphp\n" > ld-2b/resources/views/show.blade.php && php /var/www/html/laravel-doctor/bin/laravel-doctor inspect ld-2b; echo "EXIT=$?"; rm -rf /tmp/ld-2b
```
Expected: lista `no-unescaped-blade-output` y `no-logic-in-blade` con su línea; score < 100.

- [ ] **Step 6: Commit**

```bash
git add src/Console/InspectCommand.php tests/Console/InspectBladeTest.php
git commit -m "feat: cablear el track Blade en el comando inspect"
```

---

## Self-Review

**Cobertura del spec:**
- `SourceType` + `SourceFile.type` → Task 1. ✓
- `FileScanner` recoge `.blade.php` y etiqueta tipo → Task 2. ✓
- `BladeConstruct` / `BladeConstructKind` → Task 3. ✓
- `BladeScanner` (enmascarado de comentarios, 3 patrones, línea por offset) → Task 4. ✓
- `BladeRule` / `BladeRuleContext` → Task 5. ✓
- `BladeEngine` (aislamiento por regla) → Task 6. ✓
- `no-unescaped-blade-output` (warning, RawEcho con variable) → Task 7. ✓
- `no-logic-in-blade` (warning, @php o token query) → Task 8. ✓
- `BladeRuleRegistry` → Task 9. ✓
- `InspectCommand` enruta por tipo y concatena diagnósticos + e2e → Task 10. ✓
- Motor PHP / Pipeline / Score / reporters sin cambios → ninguna tarea los toca. ✓

**Placeholders:** ninguno; todo el código está completo. Tokens query y patrones regex con
valores concretos.

**Consistencia de tipos:** `BladeRule`/`BladeRuleContext`/`BladeConstruct`/`BladeConstructKind`
con firmas idénticas en todas las tareas; el `Diagnostic` que arma `BladeRuleContext` usa los
mismos campos que el del motor PHP (lo verifica el e2e de Task 10 al mezclar ambos en un solo
reporte JSON). Los tests de reglas usan `analyze()` vía `BladeEngine`. `SourceType::Php` por
defecto en `SourceFile` mantiene válidas las construcciones de 2 args del v1/2A. Regex y cálculo
de línea verificados con un probe antes de escribir el plan.
