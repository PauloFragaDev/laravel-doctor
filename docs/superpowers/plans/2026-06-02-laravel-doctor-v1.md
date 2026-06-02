# laravel-doctor v1 — Plan de implementación (Fase 1: fundación + 4 reglas)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el motor estático de laravel-doctor de punta a punta (escaneo → AST → reglas → pipeline → score → reporters → CLI → install) con una regla exemplar por categoría, dejando una API de reglas estable.

**Architecture:** Paquete Composer PHP nativo. `FileScanner` descubre archivos `.php`; `Engine` los parsea con nikic/php-parser y recorre el AST con un `RuleVisitor` que despacha cada `Rule` y mantiene una pila de ancestros; los `Diagnostic`s pasan por un `Pipeline` (dedup + orden), de ahí a `ScoreCalculator` y a los reporters (`TtyReporter` humano / `AgentReporter` JSON). El CLI (symfony/console) cablea todo; `install` copia la skill.

**Tech Stack:** PHP 8.4, nikic/php-parser ^5, symfony/console ^7, PHPUnit ^11.

**Convenciones fijas (no cambiar entre tareas):**
- Namespace raíz: `LaravelDoctor\` → `src/`. Tests: `LaravelDoctor\Tests\` → `tests/`.
- Categorías (strings exactos): `security`, `performance`, `eloquent`, `architecture`.
- Severidades: enum `Severity` con casos `Error`, `Warning`, `Info`.
- Toda clase de valor es `final readonly`.

---

### Task 1: Scaffolding del paquete Composer

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `.gitignore`
- Create: `tests/SmokeTest.php`

- [ ] **Step 1: Crear `composer.json`**

```json
{
    "name": "laravel-doctor/laravel-doctor",
    "description": "Auditor determinista para codebases Laravel: seguridad, performance, Eloquent y arquitectura.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.2",
        "nikic/php-parser": "^5.0",
        "symfony/console": "^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": { "LaravelDoctor\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "LaravelDoctor\\Tests\\": "tests/" }
    },
    "bin": ["bin/laravel-doctor"],
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Crear `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="laravel-doctor">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Crear `.gitignore`**

```
/vendor/
/.phpunit.cache/
composer.lock
```

- [ ] **Step 4: Instalar dependencias**

Run: `composer install`
Expected: crea `vendor/` y `composer.lock`, sin errores.

- [ ] **Step 5: Escribir un smoke test**

`tests/SmokeTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function test_autoloading_works(): void
    {
        $this->assertTrue(class_exists(\PhpParser\ParserFactory::class));
    }
}
```

- [ ] **Step 6: Correr el smoke test**

Run: `vendor/bin/phpunit --filter test_autoloading_works`
Expected: PASS (1 test, 1 assertion).

- [ ] **Step 7: Commit**

```bash
git add composer.json phpunit.xml .gitignore tests/SmokeTest.php
git commit -m "chore: scaffolding del paquete composer y phpunit"
```

---

### Task 2: Value objects de diagnóstico (`Severity`, `Diagnostic`)

**Files:**
- Create: `src/Diagnostics/Severity.php`
- Create: `src/Diagnostics/Diagnostic.php`
- Test: `tests/Diagnostics/DiagnosticTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Diagnostics/DiagnosticTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Diagnostics;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class DiagnosticTest extends TestCase
{
    public function test_holds_its_data(): void
    {
        $d = new Diagnostic(
            ruleId: 'no-env-outside-config',
            category: 'security',
            severity: Severity::Error,
            file: 'app/Foo.php',
            line: 12,
            message: 'env() fuera de config',
            recommendation: 'usa config()',
        );

        $this->assertSame('no-env-outside-config', $d->ruleId);
        $this->assertSame('security', $d->category);
        $this->assertSame(Severity::Error, $d->severity);
        $this->assertSame(12, $d->line);
    }

    public function test_severity_weight(): void
    {
        $this->assertSame(3, Severity::Error->weight());
        $this->assertSame(2, Severity::Warning->weight());
        $this->assertSame(1, Severity::Info->weight());
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter DiagnosticTest`
Expected: FAIL — "Class ... not found".

- [ ] **Step 3: Implementar `Severity`**

`src/Diagnostics/Severity.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function weight(): int
    {
        return match ($this) {
            Severity::Error => 3,
            Severity::Warning => 2,
            Severity::Info => 1,
        };
    }
}
```

- [ ] **Step 4: Implementar `Diagnostic`**

`src/Diagnostics/Diagnostic.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final readonly class Diagnostic
{
    public function __construct(
        public string $ruleId,
        public string $category,
        public Severity $severity,
        public string $file,
        public int $line,
        public string $message,
        public string $recommendation,
    ) {
    }
}
```

- [ ] **Step 5: Correr el test**

Run: `vendor/bin/phpunit --filter DiagnosticTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Diagnostics tests/Diagnostics
git commit -m "feat: value objects Severity y Diagnostic"
```

---

### Task 3: Metadatos de categoría (`Categories`)

**Files:**
- Create: `src/Diagnostics/Categories.php`
- Test: `tests/Diagnostics/CategoriesTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Diagnostics/CategoriesTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Diagnostics;

use LaravelDoctor\Diagnostics\Categories;
use PHPUnit\Framework\TestCase;

final class CategoriesTest extends TestCase
{
    public function test_weights(): void
    {
        $this->assertSame(4, Categories::weight(Categories::SECURITY));
        $this->assertSame(3, Categories::weight(Categories::PERFORMANCE));
        $this->assertSame(3, Categories::weight(Categories::ELOQUENT));
        $this->assertSame(2, Categories::weight(Categories::ARCHITECTURE));
    }

    public function test_unknown_category_defaults_to_one(): void
    {
        $this->assertSame(1, Categories::weight('made-up'));
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter CategoriesTest`
Expected: FAIL — "Class ... not found".

- [ ] **Step 3: Implementar `Categories`**

`src/Diagnostics/Categories.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final class Categories
{
    public const SECURITY = 'security';
    public const PERFORMANCE = 'performance';
    public const ELOQUENT = 'eloquent';
    public const ARCHITECTURE = 'architecture';

    private const WEIGHTS = [
        self::SECURITY => 4,
        self::PERFORMANCE => 3,
        self::ELOQUENT => 3,
        self::ARCHITECTURE => 2,
    ];

    public static function weight(string $category): int
    {
        return self::WEIGHTS[$category] ?? 1;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter CategoriesTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Diagnostics/Categories.php tests/Diagnostics/CategoriesTest.php
git commit -m "feat: metadatos y pesos de categoría"
```

---

### Task 4: Contrato de regla, contexto y colector (`Rule`, `RuleContext`, `DiagnosticCollector`)

**Files:**
- Create: `src/Rules/Rule.php`
- Create: `src/Diagnostics/DiagnosticCollector.php`
- Create: `src/Rules/RuleContext.php`
- Test: `tests/Rules/RuleContextTest.php`

Nota: `RuleContext` obtiene el archivo actual y la pila de ancestros del `RuleVisitor`
(Task 6). Para poder testear el contexto de forma aislada, definimos una interfaz mínima
`AncestorProvider` que el visitor implementará.

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/RuleContextTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\AncestorProvider;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\TestCase;

final class RuleContextTest extends TestCase
{
    private function fakeRule(): Rule
    {
        return new class implements Rule {
            public function id(): string { return 'fake-rule'; }
            public function title(): string { return 'Fake'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Error; }
            public function recommendation(): string { return 'arregla esto'; }
            public function enterNode(Node $node, RuleContext $context): void {}
        };
    }

    private function provider(string $file, array $ancestors): AncestorProvider
    {
        return new class($file, $ancestors) implements AncestorProvider {
            public function __construct(private string $file, private array $ancestors) {}
            public function currentFile(): string { return $this->file; }
            public function currentAncestors(): array { return $this->ancestors; }
        };
    }

    public function test_report_builds_diagnostic_from_rule_metadata(): void
    {
        $collector = new DiagnosticCollector();
        $ctx = new RuleContext($this->fakeRule(), $this->provider('app/X.php', []), $collector);

        $node = new Int_(0);
        $node->setAttribute('startLine', 7);
        $ctx->report($node, 'mensaje de impacto');

        $all = $collector->all();
        $this->assertCount(1, $all);
        $this->assertSame('fake-rule', $all[0]->ruleId);
        $this->assertSame('security', $all[0]->category);
        $this->assertSame(Severity::Error, $all[0]->severity);
        $this->assertSame('app/X.php', $all[0]->file);
        $this->assertSame(7, $all[0]->line);
        $this->assertSame('mensaje de impacto', $all[0]->message);
        $this->assertSame('arregla esto', $all[0]->recommendation);
    }

    public function test_ancestors_are_exposed(): void
    {
        $parent = new Int_(1);
        $ctx = new RuleContext($this->fakeRule(), $this->provider('app/X.php', [$parent]), new DiagnosticCollector());
        $this->assertSame([$parent], $ctx->ancestors());
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter RuleContextTest`
Expected: FAIL — interfaces/clases no existen.

- [ ] **Step 3: Implementar `Rule` y `AncestorProvider`**

`src/Rules/Rule.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Diagnostics\Severity;
use PhpParser\Node;

interface Rule
{
    public function id(): string;

    public function title(): string;

    /** Una de las constantes de LaravelDoctor\Diagnostics\Categories. */
    public function category(): string;

    public function severity(): Severity;

    public function recommendation(): string;

    /** Invocado para cada nodo del AST durante el recorrido. */
    public function enterNode(Node $node, RuleContext $context): void;
}
```

Añadir en el mismo directorio `src/Rules/AncestorProvider.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use PhpParser\Node;

interface AncestorProvider
{
    public function currentFile(): string;

    /** Ancestros del nodo en curso, del más cercano al más lejano. @return Node[] */
    public function currentAncestors(): array;
}
```

- [ ] **Step 4: Implementar `DiagnosticCollector`**

`src/Diagnostics/DiagnosticCollector.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final class DiagnosticCollector
{
    /** @var Diagnostic[] */
    private array $diagnostics = [];

    public function add(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /** @return Diagnostic[] */
    public function all(): array
    {
        return $this->diagnostics;
    }
}
```

- [ ] **Step 5: Implementar `RuleContext`**

`src/Rules/RuleContext.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use PhpParser\Node;

final class RuleContext
{
    public function __construct(
        private Rule $rule,
        private AncestorProvider $provider,
        private DiagnosticCollector $collector,
    ) {
    }

    public function report(Node $node, string $message): void
    {
        $this->collector->add(new Diagnostic(
            ruleId: $this->rule->id(),
            category: $this->rule->category(),
            severity: $this->rule->severity(),
            file: $this->provider->currentFile(),
            line: $node->getStartLine(),
            message: $message,
            recommendation: $this->rule->recommendation(),
        ));
    }

    /** @return Node[] del más cercano al más lejano */
    public function ancestors(): array
    {
        return $this->provider->currentAncestors();
    }

    public function filePath(): string
    {
        return $this->provider->currentFile();
    }
}
```

- [ ] **Step 6: Correr el test**

Run: `vendor/bin/phpunit --filter RuleContextTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add src/Rules tests/Rules src/Diagnostics/DiagnosticCollector.php
git commit -m "feat: contrato Rule, RuleContext y DiagnosticCollector"
```

---

### Task 5: Parser de PHP (`PhpAstParser`)

**Files:**
- Create: `src/Engine/PhpAstParser.php`
- Test: `tests/Engine/PhpAstParserTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Engine/PhpAstParserTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Engine;

use LaravelDoctor\Engine\PhpAstParser;
use PhpParser\Error;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\TestCase;

final class PhpAstParserTest extends TestCase
{
    public function test_parses_valid_php_into_statements(): void
    {
        $stmts = (new PhpAstParser())->parse("<?php \$x = 1;");
        $this->assertIsArray($stmts);
        $this->assertInstanceOf(Stmt::class, $stmts[0]);
    }

    public function test_throws_on_syntax_error(): void
    {
        $this->expectException(Error::class);
        (new PhpAstParser())->parse("<?php \$x = ;");
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter PhpAstParserTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `PhpAstParser`**

`src/Engine/PhpAstParser.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class PhpAstParser
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return Stmt[]
     * @throws \PhpParser\Error en error de sintaxis
     */
    public function parse(string $code): array
    {
        return $this->parser->parse($code) ?? [];
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter PhpAstParserTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Engine/PhpAstParser.php tests/Engine/PhpAstParserTest.php
git commit -m "feat: PhpAstParser sobre nikic/php-parser"
```

---

### Task 6: Visitor de reglas con pila de ancestros (`RuleVisitor`)

**Files:**
- Create: `src/Engine/RuleVisitor.php`
- Test: `tests/Engine/RuleVisitorTest.php`

El `RuleVisitor` implementa `PhpParser\NodeVisitor` y `AncestorProvider`. Por cada nodo:
despacha cada regla (aislando excepciones por regla), y mantiene la pila de ancestros
(empuja **después** de despachar, para que la regla vea solo a sus padres; saca en
`leaveNode`). Crea un `RuleContext` por regla y lo reutiliza.

- [ ] **Step 1: Escribir el test que falla**

`tests/Engine/RuleVisitorTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Engine;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Engine\PhpAstParser;
use LaravelDoctor\Engine\RuleVisitor;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeTraverser;
use PHPUnit\Framework\TestCase;

final class RuleVisitorTest extends TestCase
{
    /** Regla que reporta cada FuncCall y registra si tenía un foreach como ancestro. */
    private function recordingRule(array &$seenAncestorTypes): Rule
    {
        return new class($seenAncestorTypes) implements Rule {
            public function __construct(private array &$seen) {}
            public function id(): string { return 'rec'; }
            public function title(): string { return 'rec'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Warning; }
            public function recommendation(): string { return 'fix'; }
            public function enterNode(Node $node, RuleContext $context): void
            {
                if ($node instanceof \PhpParser\Node\Expr\FuncCall) {
                    foreach ($context->ancestors() as $a) {
                        $this->seen[] = $a::class;
                    }
                    $context->report($node, 'func call');
                }
            }
        };
    }

    public function test_dispatches_rules_and_tracks_ancestors(): void
    {
        $seen = [];
        $collector = new DiagnosticCollector();
        $visitor = new RuleVisitor([$this->recordingRule($seen)], $collector);
        $visitor->setFile('app/Demo.php');

        $stmts = (new PhpAstParser())->parse("<?php foreach (\$xs as \$x) { strlen(\$x); }");
        $t = new NodeTraverser();
        $t->addVisitor($visitor);
        $t->traverse($stmts);

        // El FuncCall strlen() está dentro de un foreach → su ancestro incluye Foreach_.
        $this->assertContains(Foreach_::class, $seen);
        $this->assertCount(1, $collector->all());
        $this->assertSame('app/Demo.php', $collector->all()[0]->file);
    }

    public function test_isolates_rule_exceptions(): void
    {
        $throwing = new class implements Rule {
            public function id(): string { return 'boom'; }
            public function title(): string { return 'boom'; }
            public function category(): string { return Categories::SECURITY; }
            public function severity(): Severity { return Severity::Error; }
            public function recommendation(): string { return 'x'; }
            public function enterNode(Node $node, RuleContext $context): void
            {
                throw new \RuntimeException('regla rota');
            }
        };

        $collector = new DiagnosticCollector();
        $visitor = new RuleVisitor([$throwing], $collector);
        $visitor->setFile('app/Demo.php');

        $stmts = (new PhpAstParser())->parse("<?php \$x = 1;");
        $t = new NodeTraverser();
        $t->addVisitor($visitor);

        // No debe propagar la excepción.
        $t->traverse($stmts);
        $this->assertSame([], $collector->all());
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter RuleVisitorTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `RuleVisitor`**

`src/Engine/RuleVisitor.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Rules\AncestorProvider;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class RuleVisitor extends NodeVisitorAbstract implements AncestorProvider
{
    private string $file = '';

    /** @var Node[] pila de ancestros, el último es el padre inmediato */
    private array $stack = [];

    /** @var array<string,RuleContext> contexto reutilizable por id de regla */
    private array $contexts = [];

    /** @var Rule[] */
    private array $rules;

    /**
     * @param Rule[] $rules
     */
    public function __construct(array $rules, private DiagnosticCollector $collector)
    {
        $this->rules = $rules;
        foreach ($rules as $rule) {
            $this->contexts[$rule->id()] = new RuleContext($rule, $this, $collector);
        }
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
        $this->stack = [];
    }

    public function enterNode(Node $node)
    {
        foreach ($this->rules as $rule) {
            try {
                $rule->enterNode($node, $this->contexts[$rule->id()]);
            } catch (\Throwable) {
                // Aislamiento por regla: una regla rota nunca tumba el run.
            }
        }
        $this->stack[] = $node;

        return null;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->stack);

        return null;
    }

    public function currentFile(): string
    {
        return $this->file;
    }

    public function currentAncestors(): array
    {
        // Más cercano primero.
        return array_reverse($this->stack);
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter RuleVisitorTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Engine/RuleVisitor.php tests/Engine/RuleVisitorTest.php
git commit -m "feat: RuleVisitor con pila de ancestros y aislamiento por regla"
```

---

### Task 7: Value object de archivo y scanner (`SourceFile`, `FileScanner`)

**Files:**
- Create: `src/Scanner/SourceFile.php`
- Create: `src/Scanner/FileScanner.php`
- Test: `tests/Scanner/FileScannerTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Scanner/FileScannerTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Scanner;

use LaravelDoctor\Scanner\FileScanner;
use PHPUnit\Framework\TestCase;

final class FileScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-scan-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
        mkdir($this->root . '/vendor/foo', 0777, true);
        file_put_contents($this->root . '/app/User.php', "<?php\n");
        file_put_contents($this->root . '/app/notes.txt', "hola");
        file_put_contents($this->root . '/vendor/foo/Skip.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_finds_php_files_and_excludes_vendor_and_non_php(): void
    {
        $files = (new FileScanner())->scan($this->root);
        $paths = array_map(fn ($f) => $f->path, $files);

        $this->assertContains($this->root . '/app/User.php', $paths);
        $this->assertNotContains($this->root . '/vendor/foo/Skip.php', $paths);
        $this->assertNotContains($this->root . '/app/notes.txt', $paths);
    }

    public function test_source_file_carries_contents(): void
    {
        $files = (new FileScanner())->scan($this->root);
        $user = array_values(array_filter($files, fn ($f) => str_ends_with($f->path, 'User.php')))[0];
        $this->assertStringContainsString('<?php', $user->contents);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter FileScannerTest`
Expected: FAIL — clases no existen.

- [ ] **Step 3: Implementar `SourceFile`**

`src/Scanner/SourceFile.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Scanner;

final readonly class SourceFile
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {
    }
}
```

- [ ] **Step 4: Implementar `FileScanner`**

`src/Scanner/FileScanner.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileScanner
{
    private const EXCLUDED_DIRS = [
        'vendor', 'node_modules', 'storage', '.git', 'bootstrap/cache', 'public',
    ];

    /**
     * @return SourceFile[]
     */
    public function scan(string $root): array
    {
        $root = rtrim($root, '/');
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();

            if (!str_ends_with($path, '.php')) {
                continue;
            }
            if ($this->isExcluded($path, $root)) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }
            $files[] = new SourceFile($path, $contents);
        }

        return $files;
    }

    private function isExcluded(string $path, string $root): bool
    {
        $relative = ltrim(substr($path, strlen($root)), '/');
        foreach (self::EXCLUDED_DIRS as $dir) {
            if ($relative === $dir || str_starts_with($relative, $dir . '/')) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 5: Correr el test**

Run: `vendor/bin/phpunit --filter FileScannerTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Scanner tests/Scanner
git commit -m "feat: FileScanner y SourceFile (solo .php en v1)"
```

---

### Task 8: Orquestador (`Engine`)

**Files:**
- Create: `src/Engine/Engine.php`
- Test: `tests/Engine/EngineTest.php`

El `Engine` recibe las reglas, y para cada `SourceFile`: parsea (aislando errores de
sintaxis con un `Diagnostic` informativo) y recorre con un `RuleVisitor`. El parámetro de
runtime se deja como `?array $runtimeManifest = null` para el hueco de boot de v1.1.

- [ ] **Step 1: Escribir el test que falla**

`tests/Engine/EngineTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Engine;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use LaravelDoctor\Scanner\SourceFile;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    private function echoRule(): Rule
    {
        return new class implements Rule {
            public function id(): string { return 'reports-echo'; }
            public function title(): string { return 'echo'; }
            public function category(): string { return Categories::ARCHITECTURE; }
            public function severity(): Severity { return Severity::Info; }
            public function recommendation(): string { return 'no uses echo'; }
            public function enterNode(Node $node, RuleContext $context): void
            {
                if ($node instanceof \PhpParser\Node\Stmt\Echo_) {
                    $context->report($node, 'echo encontrado');
                }
            }
        };
    }

    public function test_runs_rules_across_files(): void
    {
        $engine = new Engine([$this->echoRule()]);
        $diagnostics = $engine->inspect([
            new SourceFile('a.php', "<?php echo 'hi';"),
            new SourceFile('b.php', "<?php \$x = 1;"),
        ]);

        $this->assertCount(1, $diagnostics);
        $this->assertSame('reports-echo', $diagnostics[0]->ruleId);
        $this->assertSame('a.php', $diagnostics[0]->file);
    }

    public function test_unparseable_file_yields_info_diagnostic_and_continues(): void
    {
        $engine = new Engine([$this->echoRule()]);
        $diagnostics = $engine->inspect([
            new SourceFile('broken.php', "<?php echo ;"),
            new SourceFile('ok.php', "<?php echo 'hi';"),
        ]);

        $ids = array_map(fn ($d) => $d->ruleId, $diagnostics);
        $this->assertContains('parse-error', $ids);
        $this->assertContains('reports-echo', $ids); // el archivo bueno sigue analizándose
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter EngineTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `Engine`**

`src/Engine/Engine.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\DiagnosticCollector;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Scanner\SourceFile;
use PhpParser\Error;
use PhpParser\NodeTraverser;

final class Engine
{
    private PhpAstParser $parser;

    /**
     * @param Rule[] $rules
     */
    public function __construct(private array $rules)
    {
        $this->parser = new PhpAstParser();
    }

    /**
     * @param SourceFile[] $files
     * @return Diagnostic[]
     */
    public function inspect(array $files, ?array $runtimeManifest = null): array
    {
        $collector = new DiagnosticCollector();
        $visitor = new RuleVisitor($this->rules, $collector);

        foreach ($files as $file) {
            try {
                $stmts = $this->parser->parse($file->contents);
            } catch (Error $e) {
                $collector->add(new Diagnostic(
                    ruleId: 'parse-error',
                    category: Categories::ARCHITECTURE,
                    severity: Severity::Info,
                    file: $file->path,
                    line: $e->getStartLine() > 0 ? $e->getStartLine() : 1,
                    message: 'No se pudo analizar este archivo: ' . $e->getRawMessage(),
                    recommendation: 'Corrige el error de sintaxis para que laravel-doctor pueda revisarlo.',
                ));
                continue;
            }

            $visitor->setFile($file->path);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);
        }

        return $collector->all();
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter EngineTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Engine/Engine.php tests/Engine/EngineTest.php
git commit -m "feat: Engine orquesta parseo y reglas con aislamiento de errores"
```

---

### Task 9: Pipeline de diagnósticos (`Pipeline`)

**Files:**
- Create: `src/Diagnostics/Pipeline.php`
- Test: `tests/Diagnostics/PipelineTest.php`

Dedup por `(ruleId, file, line)`; orden por peso de severidad desc, luego peso de
categoría desc, luego file y line asc.

- [ ] **Step 1: Escribir el test que falla**

`tests/Diagnostics/PipelineTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Diagnostics;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Pipeline;
use LaravelDoctor\Diagnostics\Severity;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    private function d(string $rule, string $cat, Severity $sev, string $file, int $line): Diagnostic
    {
        return new Diagnostic($rule, $cat, $sev, $file, $line, 'msg', 'rec');
    }

    public function test_dedupes_same_rule_file_line(): void
    {
        $out = (new Pipeline())->process([
            $this->d('r1', Categories::SECURITY, Severity::Error, 'a.php', 10),
            $this->d('r1', Categories::SECURITY, Severity::Error, 'a.php', 10),
        ]);
        $this->assertCount(1, $out);
    }

    public function test_orders_by_severity_then_category(): void
    {
        $out = (new Pipeline())->process([
            $this->d('arch', Categories::ARCHITECTURE, Severity::Warning, 'a.php', 1),
            $this->d('sec', Categories::SECURITY, Severity::Error, 'a.php', 5),
            $this->d('perf', Categories::PERFORMANCE, Severity::Error, 'a.php', 2),
        ]);

        // Error/security primero; entre los dos Error, security (4) > performance (3).
        $this->assertSame('sec', $out[0]->ruleId);
        $this->assertSame('perf', $out[1]->ruleId);
        $this->assertSame('arch', $out[2]->ruleId);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter PipelineTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `Pipeline`**

`src/Diagnostics/Pipeline.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final class Pipeline
{
    /**
     * @param Diagnostic[] $diagnostics
     * @return Diagnostic[]
     */
    public function process(array $diagnostics): array
    {
        $deduped = $this->dedupe($diagnostics);
        usort($deduped, $this->comparator(...));

        return $deduped;
    }

    /**
     * @param Diagnostic[] $diagnostics
     * @return Diagnostic[]
     */
    private function dedupe(array $diagnostics): array
    {
        $seen = [];
        $out = [];
        foreach ($diagnostics as $d) {
            $key = $d->ruleId . '|' . $d->file . '|' . $d->line;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $d;
        }

        return $out;
    }

    private function comparator(Diagnostic $a, Diagnostic $b): int
    {
        return $b->severity->weight() <=> $a->severity->weight()
            ?: Categories::weight($b->category) <=> Categories::weight($a->category)
            ?: strcmp($a->file, $b->file)
            ?: $a->line <=> $b->line;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter PipelineTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Diagnostics/Pipeline.php tests/Diagnostics/PipelineTest.php
git commit -m "feat: Pipeline con dedup y orden por prioridad"
```

---

### Task 10: Cálculo de score (`ScoreResult`, `ScoreCalculator`)

**Files:**
- Create: `src/Score/ScoreResult.php`
- Create: `src/Score/ScoreCalculator.php`
- Test: `tests/Score/ScoreCalculatorTest.php`

Algoritmo: arranca en 100. `unit = severity.weight() * Categories::weight(cat)`. Por cada
regla con `n` ocurrencias, penalización = `unit * (1 - r^n) / (1 - r)` con `r = 0.6`
(rendimientos decrecientes). `score = max(0, round(100 - sumaPenalizaciones))`.
Etiquetas: `>=90 Healthy`, `>=70 Needs work`, `>=40 At risk`, resto `Critical`.

Verificación del número del test: 1 diagnóstico Error+security → `unit = 3*4 = 12`,
`penalty = 12*(1-0.6)/0.4 = 12` → score `88` → "Needs work".

- [ ] **Step 1: Escribir el test que falla**

`tests/Score/ScoreCalculatorTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Score;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Score\ScoreCalculator;
use PHPUnit\Framework\TestCase;

final class ScoreCalculatorTest extends TestCase
{
    private function d(string $rule, string $cat, Severity $sev): Diagnostic
    {
        return new Diagnostic($rule, $cat, $sev, 'a.php', 1, 'm', 'r');
    }

    public function test_perfect_score_with_no_diagnostics(): void
    {
        $result = (new ScoreCalculator())->score([]);
        $this->assertSame(100, $result->score);
        $this->assertSame('Healthy', $result->label);
    }

    public function test_single_security_error(): void
    {
        $result = (new ScoreCalculator())->score([
            $this->d('r', Categories::SECURITY, Severity::Error),
        ]);
        $this->assertSame(88, $result->score);
        $this->assertSame('Needs work', $result->label);
    }

    public function test_same_rule_saturates(): void
    {
        // 3 ocurrencias de la misma regla: unit=12, penalty=12*(1-0.6^3)/0.4 = 12*0.784/0.4 = 23.52 → 100-23.52 = 76.48 → 76
        $result = (new ScoreCalculator())->score([
            $this->d('r', Categories::SECURITY, Severity::Error),
            $this->d('r', Categories::SECURITY, Severity::Error),
            $this->d('r', Categories::SECURITY, Severity::Error),
        ]);
        $this->assertSame(76, $result->score);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter ScoreCalculatorTest`
Expected: FAIL — clases no existen.

- [ ] **Step 3: Implementar `ScoreResult`**

`src/Score/ScoreResult.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Score;

final readonly class ScoreResult
{
    public function __construct(
        public int $score,
        public string $label,
    ) {
    }
}
```

- [ ] **Step 4: Implementar `ScoreCalculator`**

`src/Score/ScoreCalculator.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Score;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;

final class ScoreCalculator
{
    private const DECAY = 0.6;

    /**
     * @param Diagnostic[] $diagnostics
     */
    public function score(array $diagnostics): ScoreResult
    {
        // Agrupa por regla, recordando un diagnóstico de muestra para los pesos.
        $counts = [];
        $sample = [];
        foreach ($diagnostics as $d) {
            $counts[$d->ruleId] = ($counts[$d->ruleId] ?? 0) + 1;
            $sample[$d->ruleId] ??= $d;
        }

        $penalty = 0.0;
        foreach ($counts as $ruleId => $n) {
            $d = $sample[$ruleId];
            $unit = $d->severity->weight() * Categories::weight($d->category);
            $penalty += $unit * (1 - self::DECAY ** $n) / (1 - self::DECAY);
        }

        $score = (int) max(0, round(100 - $penalty));

        return new ScoreResult($score, $this->label($score));
    }

    private function label(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Healthy',
            $score >= 70 => 'Needs work',
            $score >= 40 => 'At risk',
            default => 'Critical',
        };
    }
}
```

- [ ] **Step 5: Correr el test**

Run: `vendor/bin/phpunit --filter ScoreCalculatorTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Score tests/Score
git commit -m "feat: ScoreCalculator con penalización saturante por regla"
```

---

### Task 11: Reporter JSON para agentes (`AgentReporter`)

**Files:**
- Create: `src/Reporting/AgentReporter.php`
- Test: `tests/Reporting/AgentReporterTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Reporting/AgentReporterTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Reporting;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Reporting\AgentReporter;
use LaravelDoctor\Score\ScoreResult;
use PHPUnit\Framework\TestCase;

final class AgentReporterTest extends TestCase
{
    public function test_emits_stable_json_contract(): void
    {
        $json = (new AgentReporter())->report(
            new ScoreResult(88, 'Needs work'),
            [new Diagnostic('no-env-outside-config', Categories::SECURITY, Severity::Error, 'app/X.php', 12, 'msg', 'rec')],
        );

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(88, $data['score']);
        $this->assertSame('Needs work', $data['label']);
        $this->assertCount(1, $data['diagnostics']);
        $this->assertSame([
            'id' => 'no-env-outside-config',
            'category' => 'security',
            'severity' => 'error',
            'file' => 'app/X.php',
            'line' => 12,
            'message' => 'msg',
            'recommendation' => 'rec',
        ], $data['diagnostics'][0]);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter AgentReporterTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `AgentReporter`**

`src/Reporting/AgentReporter.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Reporting;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

final class AgentReporter
{
    /**
     * @param Diagnostic[] $diagnostics
     */
    public function report(ScoreResult $score, array $diagnostics): string
    {
        return json_encode([
            'score' => $score->score,
            'label' => $score->label,
            'diagnostics' => array_map(fn (Diagnostic $d) => [
                'id' => $d->ruleId,
                'category' => $d->category,
                'severity' => $d->severity->value,
                'file' => $d->file,
                'line' => $d->line,
                'message' => $d->message,
                'recommendation' => $d->recommendation,
            ], $diagnostics),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter AgentReporterTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add src/Reporting/AgentReporter.php tests/Reporting/AgentReporterTest.php
git commit -m "feat: AgentReporter con contrato JSON estable"
```

---

### Task 12: Reporter de terminal (`TtyReporter`)

**Files:**
- Create: `src/Reporting/TtyReporter.php`
- Test: `tests/Reporting/TtyReporterTest.php`

Salida humana sin color (el color lo maneja symfony/console en el comando). Contiene la
cabecera con score + label y una línea por diagnóstico con `severity file:line ruleId`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Reporting/TtyReporterTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Reporting;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Score\ScoreResult;
use PHPUnit\Framework\TestCase;

final class TtyReporterTest extends TestCase
{
    public function test_includes_score_and_each_diagnostic(): void
    {
        $out = (new TtyReporter())->report(
            new ScoreResult(88, 'Needs work'),
            [new Diagnostic('no-env-outside-config', Categories::SECURITY, Severity::Error, 'app/X.php', 12, 'env() fuera de config', 'usa config()')],
        );

        $this->assertStringContainsString('88', $out);
        $this->assertStringContainsString('Needs work', $out);
        $this->assertStringContainsString('app/X.php:12', $out);
        $this->assertStringContainsString('no-env-outside-config', $out);
        $this->assertStringContainsString('env() fuera de config', $out);
    }

    public function test_clean_report_when_no_diagnostics(): void
    {
        $out = (new TtyReporter())->report(new ScoreResult(100, 'Healthy'), []);
        $this->assertStringContainsString('100', $out);
        $this->assertStringContainsString('No se encontraron problemas', $out);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter TtyReporterTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `TtyReporter`**

`src/Reporting/TtyReporter.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Reporting;

use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Score\ScoreResult;

final class TtyReporter
{
    /**
     * @param Diagnostic[] $diagnostics
     */
    public function report(ScoreResult $score, array $diagnostics): string
    {
        $lines = [];
        $lines[] = sprintf('laravel-doctor — Score: %d/100 (%s)', $score->score, $score->label);
        $lines[] = '';

        if ($diagnostics === []) {
            $lines[] = 'No se encontraron problemas.';

            return implode("\n", $lines) . "\n";
        }

        foreach ($diagnostics as $d) {
            $lines[] = sprintf('[%s] %s:%d  %s', strtoupper($d->severity->value), $d->file, $d->line, $d->ruleId);
            $lines[] = '    ' . $d->message;
            $lines[] = '    → ' . $d->recommendation;
            $lines[] = '';
        }

        $lines[] = sprintf('%d problema(s) encontrado(s).', count($diagnostics));

        return implode("\n", $lines) . "\n";
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter TtyReporterTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Reporting/TtyReporter.php tests/Reporting/TtyReporterTest.php
git commit -m "feat: TtyReporter para salida humana"
```

---

### Task 13: Regla de seguridad — `no-env-outside-config`

**Files:**
- Create: `src/Rules/Security/NoEnvOutsideConfig.php`
- Test: `tests/Rules/Security/NoEnvOutsideConfigTest.php`

Dispara cuando se llama `env(...)` y el archivo NO está dentro de un directorio `config/`.
Helper de test: parsea código + corre el `Engine` con solo esta regla, sobre un `SourceFile`
de path controlado.

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Security/NoEnvOutsideConfigTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Security;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Security\NoEnvOutsideConfig;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoEnvOutsideConfigTest extends TestCase
{
    private function run(string $path, string $code): array
    {
        return (new Engine([new NoEnvOutsideConfig()]))->inspect([new SourceFile($path, $code)]);
    }

    public function test_flags_env_in_app_code(): void
    {
        $d = $this->run('app/Services/Pay.php', "<?php \$k = env('STRIPE_KEY');");
        $this->assertCount(1, $d);
        $this->assertSame('no-env-outside-config', $d[0]->ruleId);
    }

    public function test_does_not_flag_env_inside_config(): void
    {
        $d = $this->run('config/services.php', "<?php return ['key' => env('STRIPE_KEY')];");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_unrelated_calls(): void
    {
        $d = $this->run('app/Services/Pay.php', "<?php strlen('x');");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter NoEnvOutsideConfigTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Security/NoEnvOutsideConfig.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Security;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

final class NoEnvOutsideConfig implements Rule
{
    public function id(): string { return 'no-env-outside-config'; }

    public function title(): string { return 'Uso de env() fuera de config'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Mueve el valor a un archivo de config/ y léelo con config(); env() devuelve null cuando la config está cacheada.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return;
        }
        if ($node->name->toString() !== 'env') {
            return;
        }
        if ($this->isInsideConfig($context->filePath())) {
            return;
        }

        $context->report($node, 'env() fuera de config/ devuelve null con la config cacheada en producción.');
    }

    private function isInsideConfig(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_contains($normalized, '/config/') || str_starts_with($normalized, 'config/');
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoEnvOutsideConfigTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Security tests/Rules/Security
git commit -m "feat(rules): no-env-outside-config"
```

---

### Task 14: Regla de performance — `prefer-exists-over-count`

**Files:**
- Create: `src/Rules/Performance/PreferExistsOverCount.php`
- Test: `tests/Rules/Performance/PreferExistsOverCountTest.php`

Dispara en `X->count() > 0` (comparación `Greater` cuyo lado izquierdo es una llamada al
método `count` y el derecho es el literal `0`).

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Performance/PreferExistsOverCountTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Performance;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Performance\PreferExistsOverCount;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class PreferExistsOverCountTest extends TestCase
{
    private function run(string $code): array
    {
        return (new Engine([new PreferExistsOverCount()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_count_greater_than_zero(): void
    {
        $d = $this->run("<?php if (\$user->posts()->count() > 0) {}");
        $this->assertCount(1, $d);
        $this->assertSame('prefer-exists-over-count', $d[0]->ruleId);
    }

    public function test_does_not_flag_count_used_for_value(): void
    {
        $d = $this->run("<?php \$n = \$user->posts()->count();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_other_comparisons(): void
    {
        $d = $this->run("<?php if (\$user->age > 0) {}");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter PreferExistsOverCountTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Performance/PreferExistsOverCount.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Performance;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;

final class PreferExistsOverCount implements Rule
{
    public function id(): string { return 'prefer-exists-over-count'; }

    public function title(): string { return 'count() > 0 para comprobar existencia'; }

    public function category(): string { return Categories::PERFORMANCE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Usa ->exists() en vez de ->count() > 0: la DB para en cuanto encuentra una fila en lugar de contarlas todas.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof Greater) {
            return;
        }
        if (!$node->right instanceof Int_ || $node->right->value !== 0) {
            return;
        }
        $left = $node->left;
        if (!$left instanceof MethodCall || !$left->name instanceof Identifier) {
            return;
        }
        if ($left->name->toString() !== 'count') {
            return;
        }

        $context->report($node, 'count() > 0 cuenta todas las filas; ->exists() para en la primera coincidencia.');
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter PreferExistsOverCountTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Performance tests/Rules/Performance
git commit -m "feat(rules): prefer-exists-over-count"
```

---

### Task 15: Regla Eloquent — `no-save-in-loop-without-transaction`

**Files:**
- Create: `src/Rules/Eloquent/NoSaveInLoopWithoutTransaction.php`
- Test: `tests/Rules/Eloquent/NoSaveInLoopWithoutTransactionTest.php`

Dispara en una llamada a método `save`/`update` cuyo conjunto de ancestros incluye un nodo
de bucle (`Foreach_`/`For_`/`While_`/`Do_`) y NO incluye una llamada a `transaction`
(método o estático, p. ej. `DB::transaction(...)`). Usa `$context->ancestors()`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Eloquent/NoSaveInLoopWithoutTransactionTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Eloquent;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Eloquent\NoSaveInLoopWithoutTransaction;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoSaveInLoopWithoutTransactionTest extends TestCase
{
    private function run(string $code): array
    {
        return (new Engine([new NoSaveInLoopWithoutTransaction()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_save_in_loop_without_transaction(): void
    {
        $d = $this->run("<?php foreach (\$users as \$u) { \$u->save(); }");
        $this->assertCount(1, $d);
        $this->assertSame('no-save-in-loop-without-transaction', $d[0]->ruleId);
    }

    public function test_does_not_flag_save_outside_loop(): void
    {
        $d = $this->run("<?php \$u->save();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_save_in_loop_within_transaction(): void
    {
        $d = $this->run("<?php DB::transaction(function () use (\$users) { foreach (\$users as \$u) { \$u->save(); } });");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter NoSaveInLoopWithoutTransactionTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Eloquent/NoSaveInLoopWithoutTransaction.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Eloquent;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;

final class NoSaveInLoopWithoutTransaction implements Rule
{
    private const LOOP_NODES = [Foreach_::class, For_::class, While_::class, Do_::class];
    private const WRITE_METHODS = ['save', 'update'];

    public function id(): string { return 'no-save-in-loop-without-transaction'; }

    public function title(): string { return 'Escritura Eloquent en bucle sin transacción'; }

    public function category(): string { return Categories::ELOQUENT; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Envuelve el bucle en DB::transaction(): N escrituras sueltas son lentas y dejan datos inconsistentes si una falla a medias.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if (!in_array($node->name->toString(), self::WRITE_METHODS, true)) {
            return;
        }

        $ancestors = $context->ancestors();
        if (!$this->hasLoopAncestor($ancestors) || $this->hasTransactionAncestor($ancestors)) {
            return;
        }

        $context->report($node, 'Escritura en bucle sin DB::transaction(): lento e inconsistente ante fallos parciales.');
    }

    /** @param Node[] $ancestors */
    private function hasLoopAncestor(array $ancestors): bool
    {
        foreach ($ancestors as $a) {
            if (in_array($a::class, self::LOOP_NODES, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param Node[] $ancestors */
    private function hasTransactionAncestor(array $ancestors): bool
    {
        foreach ($ancestors as $a) {
            $name = null;
            if ($a instanceof StaticCall && $a->name instanceof Identifier) {
                $name = $a->name->toString();
            } elseif ($a instanceof MethodCall && $a->name instanceof Identifier) {
                $name = $a->name->toString();
            }
            if ($name === 'transaction') {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoSaveInLoopWithoutTransactionTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Eloquent tests/Rules/Eloquent
git commit -m "feat(rules): no-save-in-loop-without-transaction"
```

---

### Task 16: Regla de arquitectura — `prefer-form-request-validation`

**Files:**
- Create: `src/Rules/Architecture/PreferFormRequestValidation.php`
- Test: `tests/Rules/Architecture/PreferFormRequestValidationTest.php`

Dispara en `$request->validate([...])` (MethodCall `validate` sobre una variable llamada
`request`).

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Architecture/PreferFormRequestValidationTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Architecture;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Architecture\PreferFormRequestValidation;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class PreferFormRequestValidationTest extends TestCase
{
    private function run(string $code): array
    {
        return (new Engine([new PreferFormRequestValidation()]))->inspect([new SourceFile('app/Http/Controllers/X.php', $code)]);
    }

    public function test_flags_inline_request_validate(): void
    {
        $d = $this->run("<?php \$request->validate(['name' => 'required']);");
        $this->assertCount(1, $d);
        $this->assertSame('prefer-form-request-validation', $d[0]->ruleId);
    }

    public function test_does_not_flag_validate_on_other_var(): void
    {
        $d = $this->run("<?php \$validator->validate();");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter PreferFormRequestValidationTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Architecture/PreferFormRequestValidation.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Architecture;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

final class PreferFormRequestValidation implements Rule
{
    public function id(): string { return 'prefer-form-request-validation'; }

    public function title(): string { return 'Validación inline en el controller'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Info; }

    public function recommendation(): string
    {
        return 'Extrae la validación a un Form Request: deja el controller delgado y reutiliza las reglas.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if ($node->name->toString() !== 'validate') {
            return;
        }
        if (!$node->var instanceof Variable || $node->var->name !== 'request') {
            return;
        }

        $context->report($node, '$request->validate() inline acopla validación y controller; un Form Request lo separa.');
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter PreferFormRequestValidationTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Architecture tests/Rules/Architecture
git commit -m "feat(rules): prefer-form-request-validation"
```

---

### Task 17: Registro de reglas (`RuleRegistry`)

**Files:**
- Create: `src/Rules/RuleRegistry.php`
- Test: `tests/Rules/RuleRegistryTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/RuleRegistryTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules;

use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleRegistry;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase
{
    public function test_returns_all_v1_rules_with_unique_ids(): void
    {
        $rules = RuleRegistry::all();

        $this->assertContainsOnlyInstancesOf(Rule::class, $rules);
        $this->assertGreaterThanOrEqual(4, count($rules));

        $ids = array_map(fn (Rule $r) => $r->id(), $rules);
        $this->assertSame($ids, array_unique($ids), 'Los ids de regla deben ser únicos');

        $this->assertContains('no-env-outside-config', $ids);
        $this->assertContains('prefer-exists-over-count', $ids);
        $this->assertContains('no-save-in-loop-without-transaction', $ids);
        $this->assertContains('prefer-form-request-validation', $ids);
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter RuleRegistryTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `RuleRegistry`**

`src/Rules/RuleRegistry.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Rules\Architecture\PreferFormRequestValidation;
use LaravelDoctor\Rules\Eloquent\NoSaveInLoopWithoutTransaction;
use LaravelDoctor\Rules\Performance\PreferExistsOverCount;
use LaravelDoctor\Rules\Security\NoEnvOutsideConfig;

final class RuleRegistry
{
    /**
     * @return Rule[]
     */
    public static function all(): array
    {
        return [
            new NoEnvOutsideConfig(),
            new PreferExistsOverCount(),
            new NoSaveInLoopWithoutTransaction(),
            new PreferFormRequestValidation(),
        ];
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter RuleRegistryTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/RuleRegistry.php tests/Rules/RuleRegistryTest.php
git commit -m "feat: RuleRegistry con las 4 reglas del v1"
```

---

### Task 18: Comando `inspect` y entrypoint CLI

**Files:**
- Create: `src/Console/InspectCommand.php`
- Create: `bin/laravel-doctor`
- Test: `tests/Console/InspectCommandTest.php`

El comando: arg opcional `path` (default cwd), opción `--json`. Cablea Scanner → Engine →
Pipeline → Score → reporter. Exit `1` si hay algún diagnóstico de severidad `error`, si no `0`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Console/InspectCommandTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InspectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InspectCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-cmd-' . uniqid();
        mkdir($this->root . '/app', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_json_output_and_failure_exit_on_error(): void
    {
        file_put_contents($this->root . '/app/Pay.php', "<?php \$k = env('X');");
        $tester = new CommandTester(new InspectCommand());

        $exit = $tester->execute(['path' => $this->root, '--json' => true]);

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('no-env-outside-config', $data['diagnostics'][0]['id']);
        $this->assertSame(1, $exit); // hay un diagnóstico de severidad error
    }

    public function test_clean_project_exits_zero(): void
    {
        file_put_contents($this->root . '/app/Ok.php', "<?php \$x = 1;");
        $tester = new CommandTester(new InspectCommand());

        $exit = $tester->execute(['path' => $this->root]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('100', $tester->getDisplay());
    }
}
```

- [ ] **Step 2: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter InspectCommandTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar `InspectCommand`**

`src/Console/InspectCommand.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Diagnostics\Pipeline;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Reporting\AgentReporter;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Rules\RuleRegistry;
use LaravelDoctor\Scanner\FileScanner;
use LaravelDoctor\Score\ScoreCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'inspect', description: 'Audita un codebase Laravel y muestra un score con los hallazgos.')]
final class InspectCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Directorio a analizar', getcwd())
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emite el reporte como JSON (para agentes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');

        $files = (new FileScanner())->scan($path);
        $diagnostics = (new Engine(RuleRegistry::all()))->inspect($files);
        $diagnostics = (new Pipeline())->process($diagnostics);
        $score = (new ScoreCalculator())->score($diagnostics);

        $report = $input->getOption('json')
            ? (new AgentReporter())->report($score, $diagnostics)
            : (new TtyReporter())->report($score, $diagnostics);

        $output->write($report);

        foreach ($diagnostics as $d) {
            if ($d->severity === Severity::Error) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Implementar el entrypoint `bin/laravel-doctor`**

`bin/laravel-doctor`:
```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        break;
    }
}

use LaravelDoctor\Console\InspectCommand;
use LaravelDoctor\Console\InstallCommand;
use Symfony\Component\Console\Application;

$app = new Application('laravel-doctor', '0.1.0');
$inspect = new InspectCommand();
$app->add($inspect);
$app->add(new InstallCommand());
$app->setDefaultCommand((string) $inspect->getName());
$app->run();
```

Nota: `InstallCommand` se crea en la Task 19; hasta entonces el entrypoint no se ejecuta
en tests (los tests usan `CommandTester` directo sobre `InspectCommand`). Haz `chmod +x bin/laravel-doctor`.

- [ ] **Step 5: Correr el test**

Run: `vendor/bin/phpunit --filter InspectCommandTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
chmod +x bin/laravel-doctor
git add src/Console/InspectCommand.php bin/laravel-doctor tests/Console/InspectCommandTest.php
git commit -m "feat: comando inspect y entrypoint CLI"
```

---

### Task 19: Comando `install` y la skill del agente

**Files:**
- Create: `skills/laravel-doctor/SKILL.md`
- Create: `src/Console/InstallCommand.php`
- Test: `tests/Console/InstallCommandTest.php`

Para el v1, `install` soporta el target **Claude Code**: copia la skill a
`<destino>/.claude/skills/laravel-doctor/SKILL.md`. El destino por defecto es el cwd; se
puede pasar como argumento (clave para testear).

- [ ] **Step 1: Crear la skill**

`skills/laravel-doctor/SKILL.md`:
```markdown
---
name: laravel-doctor
description: Use after running laravel-doctor to read its findings and fix the reported Laravel issues (security, performance, Eloquent, architecture).
---

# laravel-doctor — arreglar hallazgos

Cuando trabajes en un proyecto Laravel con `laravel-doctor` instalado:

1. Ejecuta `./vendor/bin/laravel-doctor --json` en la raíz del proyecto.
2. Parsea el JSON. Tiene la forma:
   `{ "score": int, "label": string, "diagnostics": [{ id, category, severity, file, line, message, recommendation }] }`.
3. Para cada diagnóstico, abre `file` en la línea `line` y aplica el arreglo que indica
   `recommendation`. El campo `message` explica el impacto real del problema.
4. Prioriza por `severity` (`error` antes que `warning` antes que `info`) y, a igualdad,
   por `category` (`security` primero).
5. Tras aplicar los arreglos, vuelve a ejecutar `laravel-doctor --json` y confirma que el
   `score` sube y que los diagnósticos resueltos desaparecen.

No desactives reglas para subir el score: arregla la causa.
```

- [ ] **Step 2: Escribir el test que falla**

`tests/Console/InstallCommandTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Console;

use LaravelDoctor\Console\InstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InstallCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-install-' . uniqid();
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_installs_skill_for_claude_code(): void
    {
        $tester = new CommandTester(new InstallCommand());
        $exit = $tester->execute(['target' => $this->root]);

        $dest = $this->root . '/.claude/skills/laravel-doctor/SKILL.md';
        $this->assertSame(0, $exit);
        $this->assertFileExists($dest);
        $this->assertStringContainsString('laravel-doctor --json', file_get_contents($dest));
    }
}
```

- [ ] **Step 3: Correr el test para verque falla**

Run: `vendor/bin/phpunit --filter InstallCommandTest`
Expected: FAIL — clase no existe.

- [ ] **Step 4: Implementar `InstallCommand`**

`src/Console/InstallCommand.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'install', description: 'Instala la skill de laravel-doctor para tu agente de IA.')]
final class InstallCommand extends Command
{
    private const SKILL_SOURCE = __DIR__ . '/../../skills/laravel-doctor/SKILL.md';

    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::OPTIONAL, 'Directorio del proyecto', getcwd());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = rtrim((string) $input->getArgument('target'), '/');
        $destDir = $target . '/.claude/skills/laravel-doctor';

        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            $output->writeln('<error>No se pudo crear el directorio de la skill.</error>');

            return Command::FAILURE;
        }

        $contents = file_get_contents(self::SKILL_SOURCE);
        if ($contents === false) {
            $output->writeln('<error>No se encontró la skill de origen.</error>');

            return Command::FAILURE;
        }

        file_put_contents($destDir . '/SKILL.md', $contents);
        $output->writeln('Skill instalada en ' . $destDir . '/SKILL.md');

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 5: Correr el test**

Run: `vendor/bin/phpunit --filter InstallCommandTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add skills src/Console/InstallCommand.php tests/Console/InstallCommandTest.php
git commit -m "feat: comando install y skill para agentes"
```

---

### Task 20: Test end-to-end y verificación final

**Files:**
- Create: `tests/EndToEndTest.php`

- [ ] **Step 1: Escribir el test end-to-end**

`tests/EndToEndTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests;

use LaravelDoctor\Console\InspectCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class EndToEndTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ld-e2e-' . uniqid();
        mkdir($this->root . '/app/Http/Controllers', 0777, true);
        mkdir($this->root . '/config', 0777, true);

        // Dispara no-env-outside-config (error) y prefer-form-request-validation (info).
        file_put_contents($this->root . '/app/Http/Controllers/PayController.php',
            "<?php\nclass PayController {\n  public function store(\$request) {\n    \$k = env('STRIPE');\n    \$request->validate(['a' => 'required']);\n  }\n}\n");
        // env() dentro de config NO debe dispararse.
        file_put_contents($this->root . '/config/services.php', "<?php return ['k' => env('STRIPE')];");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_full_run_produces_expected_findings_and_score(): void
    {
        $tester = new CommandTester(new InspectCommand());
        $exit = $tester->execute(['path' => $this->root, '--json' => true]);

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_map(fn ($d) => $d['id'], $data['diagnostics']);

        $this->assertContains('no-env-outside-config', $ids);
        $this->assertContains('prefer-form-request-validation', $ids);
        $this->assertSame(1, $exit);              // hay un error → exit 1
        $this->assertLessThan(100, $data['score']);
        // El env() dentro de config no cuenta: solo 1 hallazgo de esa regla.
        $this->assertCount(1, array_filter($ids, fn ($id) => $id === 'no-env-outside-config'));
    }
}
```

- [ ] **Step 2: Correr toda la suite**

Run: `vendor/bin/phpunit`
Expected: PASS — todos los tests verdes.

- [ ] **Step 3: Verificación manual del binario**

Run: `cd /tmp && rm -rf ld-demo && mkdir -p ld-demo/app && printf "<?php\n\$k = env('X');\n" > ld-demo/app/A.php && php /var/www/html/laravel-doctor/bin/laravel-doctor inspect ld-demo`
Expected: imprime un score < 100 y lista `no-env-outside-config`. Exit code 1 (`echo $?`).

- [ ] **Step 4: Commit**

```bash
git add tests/EndToEndTest.php
git commit -m "test: cobertura end-to-end del flujo completo"
```

---

## Self-Review (cobertura del spec)

- **CLI + score** → Tasks 10, 18 (score local + comando inspect). ✓
- **Skill para agentes** → Task 19 (install + SKILL.md + contrato JSON de Task 11). ✓
- **4 categorías, 1 regla exemplar c/u** → Tasks 13 (security), 14 (performance), 15 (eloquent), 16 (architecture). ✓
- **Motor propio sobre nikic/php-parser** → Tasks 5, 6, 8. ✓
- **Pipeline (dedup + orden)** → Task 9. ✓
- **Manejo de errores (parse error aislado, regla aislada)** → Tasks 8 y 6. ✓
- **Hueco de boot (RuntimeManifest)** → `Engine::inspect(?array $runtimeManifest = null)` en Task 8. ✓
- **Exit codes** → Task 18. ✓
- **Testing por regla + pipeline + score + reporters + e2e** → Tasks 9-20. ✓

**Diferido a Plan 2 (consciente, no es un hueco):** parser Blade y reglas Blade
(`no-unescaped-blade-output`, `no-logic-in-blade`), y las otras 8 reglas estáticas del set
del spec. El `FileScanner` de v1 solo recoge `.php` (Task 7), y se ampliará a `.blade.php`
en el Plan 2 junto con `BladeParser`.

**Consistencia de tipos:** `Rule`, `RuleContext`, `AncestorProvider`, `Diagnostic`,
`Severity`, `Categories`, `ScoreResult` y sus firmas se usan idénticos en todas las tareas
que los referencian. El contrato JSON de `AgentReporter` (Task 11) coincide con lo que lee
la skill (Task 19) y lo que verifica el e2e (Task 20).
