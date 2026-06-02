# laravel-doctor Plan 2A — Implementación (6 reglas estáticas)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir 6 reglas estáticas de alta señal (2 seguridad, 2 performance, 2 arquitectura) sobre la API de reglas ya estable del v1, y registrarlas.

**Architecture:** Cada regla es una clase nueva que implementa el contrato `LaravelDoctor\Rules\Rule` existente y reporta vía `RuleContext`. No se toca motor, pipeline, score ni reporters. Al final se amplía `RuleRegistry::all()` de 4 a 10 reglas.

**Tech Stack:** PHP 8.4, nikic/php-parser ^5, PHPUnit ^11. Trabajar desde `/var/www/html/laravel-doctor` (rama `feat/plan-2a`).

**Convenciones fijas (igual que en el v1):**
- El contrato `Rule` exige: `id()`, `title()`, `category()` (constante de `Categories`), `severity()` (`Severity`), `recommendation()`, `enterNode(\PhpParser\Node $node, RuleContext $context)`.
- `RuleContext` ofrece: `report(\PhpParser\Node $node, string $message)`, `ancestors(): Node[]` (del más cercano al más lejano), `filePath(): string`.
- Los tests usan un helper **`analyze()`** (NO `run()` — choca con `TestCase::run()` que es `final`).
- Patrón de test por regla: `(new Engine([new LaRegla()]))->inspect([new SourceFile($path, $code)])`.

---

### Task 1: Regla `no-mass-assignment-guarded-empty` (seguridad)

**Files:**
- Create: `src/Rules/Security/NoMassAssignmentGuardedEmpty.php`
- Test: `tests/Rules/Security/NoMassAssignmentGuardedEmptyTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Security/NoMassAssignmentGuardedEmptyTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Security;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Security\NoMassAssignmentGuardedEmpty;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoMassAssignmentGuardedEmptyTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoMassAssignmentGuardedEmpty()]))->inspect([new SourceFile('app/Models/User.php', $code)]);
    }

    public function test_flags_empty_guarded(): void
    {
        $d = $this->analyze("<?php class User { protected \$guarded = []; }");
        $this->assertCount(1, $d);
        $this->assertSame('no-mass-assignment-guarded-empty', $d[0]->ruleId);
    }

    public function test_flags_unguard_call(): void
    {
        $d = $this->analyze("<?php User::unguard();");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_nonempty_guarded(): void
    {
        $d = $this->analyze("<?php class User { protected \$guarded = ['id']; }");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_empty_fillable(): void
    {
        $d = $this->analyze("<?php class User { protected \$fillable = []; }");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoMassAssignmentGuardedEmptyTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Security/NoMassAssignmentGuardedEmpty.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Security;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Property;

final class NoMassAssignmentGuardedEmpty implements Rule
{
    public function id(): string { return 'no-mass-assignment-guarded-empty'; }

    public function title(): string { return 'Asignación en masa sin restricción'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Define $fillable con los campos permitidos en vez de $guarded = [] / unguard(); así el request no puede escribir columnas sensibles.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if ($node instanceof Property) {
            foreach ($node->props as $item) {
                if ($item->name->toString() === 'guarded'
                    && $item->default instanceof Array_
                    && $item->default->items === []) {
                    $context->report($node, '$guarded = [] deja todos los campos asignables en masa.');

                    return;
                }
            }

            return;
        }

        if (($node instanceof StaticCall || $node instanceof MethodCall)
            && $node->name instanceof Identifier
            && $node->name->toString() === 'unguard') {
            $context->report($node, 'unguard() desactiva la protección de asignación en masa globalmente.');
        }
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoMassAssignmentGuardedEmptyTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Security/NoMassAssignmentGuardedEmpty.php tests/Rules/Security/NoMassAssignmentGuardedEmptyTest.php
git commit -m "feat(rules): no-mass-assignment-guarded-empty"
```

---

### Task 2: Regla `no-raw-sql-interpolation` (seguridad)

**Files:**
- Create: `src/Rules/Security/NoRawSqlInterpolation.php`
- Test: `tests/Rules/Security/NoRawSqlInterpolationTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Security/NoRawSqlInterpolationTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Security;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Security\NoRawSqlInterpolation;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoRawSqlInterpolationTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoRawSqlInterpolation()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_interpolated_string(): void
    {
        $d = $this->analyze("<?php \$q->whereRaw(\"a = \$id\");");
        $this->assertCount(1, $d);
        $this->assertSame('no-raw-sql-interpolation', $d[0]->ruleId);
    }

    public function test_flags_concatenation_with_variable(): void
    {
        $d = $this->analyze("<?php \$q->whereRaw('a = ' . \$id);");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_literal_string(): void
    {
        $d = $this->analyze("<?php \$q->whereRaw('a = 1');");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_db_raw_literal(): void
    {
        $d = $this->analyze("<?php DB::raw('count(*)');");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoRawSqlInterpolationTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Security/NoRawSqlInterpolation.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Security;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\InterpolatedString;

final class NoRawSqlInterpolation implements Rule
{
    private const RAW_METHODS = [
        'whereRaw', 'havingRaw', 'orderByRaw', 'selectRaw', 'raw', 'statement', 'unprepared',
    ];

    public function id(): string { return 'no-raw-sql-interpolation'; }

    public function title(): string { return 'SQL crudo con interpolación de variables'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Usa bindings parametrizados (segundo argumento de whereRaw / "?") en vez de interpolar variables en el SQL: evita inyección.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return;
        }
        if (!$node->name instanceof Identifier || !in_array($node->name->toString(), self::RAW_METHODS, true)) {
            return;
        }
        $first = $node->args[0] ?? null;
        if ($first === null || !$first instanceof Node\Arg) {
            return;
        }

        if ($this->isInterpolated($first->value)) {
            $context->report($node, 'SQL crudo con una variable interpolada: vector de inyección SQL.');
        }
    }

    private function isInterpolated(Node $value): bool
    {
        if ($value instanceof InterpolatedString) {
            return true;
        }
        if ($value instanceof Concat) {
            return $this->hasVariable($value);
        }

        return false;
    }

    private function hasVariable(Node $node): bool
    {
        if ($node instanceof Variable) {
            return true;
        }
        if ($node instanceof Concat) {
            return $this->hasVariable($node->left) || $this->hasVariable($node->right);
        }

        return false;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoRawSqlInterpolationTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Security/NoRawSqlInterpolation.php tests/Rules/Security/NoRawSqlInterpolationTest.php
git commit -m "feat(rules): no-raw-sql-interpolation"
```

---

### Task 3: Regla `no-query-in-loop` (performance)

**Files:**
- Create: `src/Rules/Performance/NoQueryInLoop.php`
- Test: `tests/Rules/Performance/NoQueryInLoopTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Performance/NoQueryInLoopTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Performance;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Performance\NoQueryInLoop;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoQueryInLoopTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoQueryInLoop()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_query_inside_foreach(): void
    {
        // En User::query()->find() el `find` es un MethodCall (no StaticCall),
        // que es lo que la regla detecta.
        $d = $this->analyze("<?php foreach (\$ids as \$id) { \$u = User::query()->find(\$id); }");
        $this->assertCount(1, $d);
        $this->assertSame('no-query-in-loop', $d[0]->ruleId);
    }

    public function test_does_not_flag_query_outside_loop(): void
    {
        $d = $this->analyze("<?php \$u = User::query()->get();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_non_query_in_loop(): void
    {
        $d = $this->analyze("<?php foreach (\$users as \$u) { echo \$u->name; }");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoQueryInLoopTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Performance/NoQueryInLoop.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Performance;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;

final class NoQueryInLoop implements Rule
{
    private const LOOP_NODES = [Foreach_::class, For_::class, While_::class, Do_::class];
    private const QUERY_METHODS = ['get', 'first', 'find', 'count', 'pluck', 'paginate', 'sum', 'value'];

    public function id(): string { return 'no-query-in-loop'; }

    public function title(): string { return 'Consulta a la base de datos dentro de un bucle'; }

    public function category(): string { return Categories::PERFORMANCE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Carga los datos antes del bucle (eager loading con with(), o whereIn()): una query por iteración es el patrón N+1.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if (!in_array($node->name->toString(), self::QUERY_METHODS, true)) {
            return;
        }
        foreach ($context->ancestors() as $ancestor) {
            if (in_array($ancestor::class, self::LOOP_NODES, true)) {
                $context->report($node, 'Consulta dentro de un bucle: provoca N+1 (una query por iteración).');

                return;
            }
        }
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoQueryInLoopTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Performance/NoQueryInLoop.php tests/Rules/Performance/NoQueryInLoopTest.php
git commit -m "feat(rules): no-query-in-loop"
```

---

### Task 4: Regla `no-all-then-filter` (performance)

**Files:**
- Create: `src/Rules/Performance/NoAllThenFilter.php`
- Test: `tests/Rules/Performance/NoAllThenFilterTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Performance/NoAllThenFilterTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Performance;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Performance\NoAllThenFilter;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoAllThenFilterTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoAllThenFilter()]))->inspect([new SourceFile('app/X.php', $code)]);
    }

    public function test_flags_all_then_filter(): void
    {
        $d = $this->analyze("<?php User::all()->filter(fn (\$u) => \$u->active);");
        $this->assertCount(1, $d);
        $this->assertSame('no-all-then-filter', $d[0]->ruleId);
    }

    public function test_flags_all_then_where(): void
    {
        $d = $this->analyze("<?php \$c->all()->where('active', true);");
        $this->assertCount(1, $d);
    }

    public function test_does_not_flag_plain_all(): void
    {
        $d = $this->analyze("<?php User::all();");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_filter_on_query_result(): void
    {
        $d = $this->analyze("<?php User::query()->get()->filter(fn (\$u) => \$u->active);");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoAllThenFilterTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Performance/NoAllThenFilter.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Performance;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;

final class NoAllThenFilter implements Rule
{
    private const COLLECTION_METHODS = ['filter', 'where', 'map', 'reject', 'sortBy'];

    public function id(): string { return 'no-all-then-filter'; }

    public function title(): string { return 'all() seguido de filtrado en PHP'; }

    public function category(): string { return Categories::PERFORMANCE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Filtra en la base de datos con where()/whereIn() antes de traer los datos; all()->filter() carga toda la tabla en memoria.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return;
        }
        if (!in_array($node->name->toString(), self::COLLECTION_METHODS, true)) {
            return;
        }

        $receiver = $node->var;
        $isAll = ($receiver instanceof MethodCall || $receiver instanceof StaticCall)
            && $receiver->name instanceof Identifier
            && $receiver->name->toString() === 'all';

        if ($isAll) {
            $context->report($node, 'all()->' . $node->name->toString() . '() trae toda la tabla y filtra en PHP.');
        }
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoAllThenFilterTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Performance/NoAllThenFilter.php tests/Rules/Performance/NoAllThenFilterTest.php
git commit -m "feat(rules): no-all-then-filter"
```

---

### Task 5: Regla `no-fat-controller-method` (arquitectura)

**Files:**
- Create: `src/Rules/Architecture/NoFatControllerMethod.php`
- Test: `tests/Rules/Architecture/NoFatControllerMethodTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Architecture/NoFatControllerMethodTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Architecture;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Architecture\NoFatControllerMethod;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoFatControllerMethodTest extends TestCase
{
    private function analyze(string $code): array
    {
        return (new Engine([new NoFatControllerMethod()]))->inspect([new SourceFile('app/Http/Controllers/X.php', $code)]);
    }

    private function longBody(): string
    {
        return str_repeat("        \$x = 1;\n", 45);
    }

    public function test_flags_long_method_in_controller(): void
    {
        $code = "<?php\nclass UserController {\n    public function index() {\n" . $this->longBody() . "    }\n}\n";
        $d = $this->analyze($code);
        $this->assertCount(1, $d);
        $this->assertSame('no-fat-controller-method', $d[0]->ruleId);
    }

    public function test_does_not_flag_short_method_in_controller(): void
    {
        $code = "<?php\nclass UserController {\n    public function index() {\n        return 1;\n    }\n}\n";
        $d = $this->analyze($code);
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_long_method_in_non_controller(): void
    {
        $code = "<?php\nclass UserService {\n    public function index() {\n" . $this->longBody() . "    }\n}\n";
        $d = $this->analyze($code);
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoFatControllerMethodTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Architecture/NoFatControllerMethod.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Architecture;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

final class NoFatControllerMethod implements Rule
{
    private const MAX_METHOD_LINES = 40;

    public function id(): string { return 'no-fat-controller-method'; }

    public function title(): string { return 'Método de controller demasiado largo'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Extrae la lógica a una Action, un servicio o el modelo; el controller debería orquestar, no contener la lógica de negocio.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof ClassMethod) {
            return;
        }
        if (!$this->isInsideController($context->ancestors())) {
            return;
        }
        if (($node->getEndLine() - $node->getStartLine()) > self::MAX_METHOD_LINES) {
            $context->report($node, 'Método de controller de más de ' . self::MAX_METHOD_LINES . ' líneas: demasiada lógica para un controller.');
        }
    }

    /** @param Node[] $ancestors */
    private function isInsideController(array $ancestors): bool
    {
        foreach ($ancestors as $ancestor) {
            if ($ancestor instanceof Class_
                && $ancestor->name !== null
                && str_ends_with($ancestor->name->toString(), 'Controller')) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoFatControllerMethodTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Architecture/NoFatControllerMethod.php tests/Rules/Architecture/NoFatControllerMethodTest.php
git commit -m "feat(rules): no-fat-controller-method"
```

---

### Task 6: Regla `no-business-logic-in-route-closure` (arquitectura)

**Files:**
- Create: `src/Rules/Architecture/NoBusinessLogicInRouteClosure.php`
- Test: `tests/Rules/Architecture/NoBusinessLogicInRouteClosureTest.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/Rules/Architecture/NoBusinessLogicInRouteClosureTest.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Rules\Architecture;

use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\Architecture\NoBusinessLogicInRouteClosure;
use LaravelDoctor\Scanner\SourceFile;
use PHPUnit\Framework\TestCase;

final class NoBusinessLogicInRouteClosureTest extends TestCase
{
    private function analyze(string $path, string $code): array
    {
        return (new Engine([new NoBusinessLogicInRouteClosure()]))->inspect([new SourceFile($path, $code)]);
    }

    private const BIG_CLOSURE = 'function () { $a=1; $b=2; $c=3; $d=4; $e=5; $f=6; }';

    public function test_flags_big_closure_in_routes_file(): void
    {
        $d = $this->analyze('routes/web.php', "<?php Route::get('/x', " . self::BIG_CLOSURE . ");");
        $this->assertCount(1, $d);
        $this->assertSame('no-business-logic-in-route-closure', $d[0]->ruleId);
    }

    public function test_does_not_flag_small_closure(): void
    {
        $d = $this->analyze('routes/web.php', "<?php Route::get('/x', function () { return 1; });");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_big_closure_outside_routes(): void
    {
        $d = $this->analyze('app/X.php', "<?php Route::get('/x', " . self::BIG_CLOSURE . ");");
        $this->assertCount(0, $d);
    }

    public function test_does_not_flag_closure_not_passed_to_route(): void
    {
        $d = $this->analyze('routes/web.php', "<?php \$fn = " . self::BIG_CLOSURE . ";");
        $this->assertCount(0, $d);
    }
}
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter NoBusinessLogicInRouteClosureTest`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Implementar la regla**

`src/Rules/Architecture/NoBusinessLogicInRouteClosure.php`:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Architecture;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Rules\Rule;
use LaravelDoctor\Rules\RuleContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

final class NoBusinessLogicInRouteClosure implements Rule
{
    private const MAX_CLOSURE_STMTS = 5;
    private const ROUTE_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'any', 'match', 'options'];

    public function id(): string { return 'no-business-logic-in-route-closure'; }

    public function title(): string { return 'Lógica de negocio en una closure de ruta'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Info; }

    public function recommendation(): string
    {
        return 'Mueve la lógica a un controller (Route::get(..., [Controller::class, "método"])); las closures de ruta no son testeables ni cacheables con route:cache.';
    }

    public function enterNode(Node $node, RuleContext $context): void
    {
        if (!$node instanceof Closure) {
            return;
        }
        if (count($node->stmts) <= self::MAX_CLOSURE_STMTS) {
            return;
        }
        if (!str_contains(str_replace('\\', '/', $context->filePath()), 'routes/')) {
            return;
        }
        if (!$this->isRouteRegistration($context->ancestors())) {
            return;
        }

        $context->report($node, 'Closure de ruta con más de ' . self::MAX_CLOSURE_STMTS . ' sentencias: la lógica debería vivir en un controller.');
    }

    /** @param Node[] $ancestors */
    private function isRouteRegistration(array $ancestors): bool
    {
        foreach ($ancestors as $ancestor) {
            if ($ancestor instanceof StaticCall
                && $ancestor->class instanceof Name
                && $ancestor->class->getLast() === 'Route'
                && $ancestor->name instanceof Identifier
                && in_array($ancestor->name->toString(), self::ROUTE_METHODS, true)) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/phpunit --filter NoBusinessLogicInRouteClosureTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Rules/Architecture/NoBusinessLogicInRouteClosure.php tests/Rules/Architecture/NoBusinessLogicInRouteClosureTest.php
git commit -m "feat(rules): no-business-logic-in-route-closure"
```

---

### Task 7: Registrar las 6 reglas en `RuleRegistry`

**Files:**
- Modify: `src/Rules/RuleRegistry.php`
- Modify: `tests/Rules/RuleRegistryTest.php`

- [ ] **Step 1: Actualizar el test (debe fallar)**

Reemplaza el cuerpo de `test_returns_all_v1_rules_with_unique_ids` en
`tests/Rules/RuleRegistryTest.php` por esta versión, que ahora exige 10 reglas y fija los 6
ids nuevos:

```php
    public function test_returns_all_v1_rules_with_unique_ids(): void
    {
        $rules = RuleRegistry::all();

        $this->assertContainsOnlyInstancesOf(Rule::class, $rules);
        $this->assertCount(10, $rules);

        $ids = array_map(fn (Rule $r) => $r->id(), $rules);
        $this->assertSame($ids, array_unique($ids), 'Los ids de regla deben ser únicos');

        foreach ([
            'no-env-outside-config',
            'prefer-exists-over-count',
            'no-save-in-loop-without-transaction',
            'prefer-form-request-validation',
            'no-mass-assignment-guarded-empty',
            'no-raw-sql-interpolation',
            'no-query-in-loop',
            'no-all-then-filter',
            'no-fat-controller-method',
            'no-business-logic-in-route-closure',
        ] as $expected) {
            $this->assertContains($expected, $ids);
        }
    }
```

- [ ] **Step 2: Correr el test para ver que falla**

Run: `vendor/bin/phpunit --filter RuleRegistryTest`
Expected: FAIL — `assertCount(10, ...)` falla (hay 4) y faltan los ids nuevos.

- [ ] **Step 3: Actualizar `RuleRegistry`**

Reemplaza `src/Rules/RuleRegistry.php` por:
```php
<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules;

use LaravelDoctor\Rules\Architecture\NoBusinessLogicInRouteClosure;
use LaravelDoctor\Rules\Architecture\NoFatControllerMethod;
use LaravelDoctor\Rules\Architecture\PreferFormRequestValidation;
use LaravelDoctor\Rules\Eloquent\NoSaveInLoopWithoutTransaction;
use LaravelDoctor\Rules\Performance\NoAllThenFilter;
use LaravelDoctor\Rules\Performance\NoQueryInLoop;
use LaravelDoctor\Rules\Performance\PreferExistsOverCount;
use LaravelDoctor\Rules\Security\NoEnvOutsideConfig;
use LaravelDoctor\Rules\Security\NoMassAssignmentGuardedEmpty;
use LaravelDoctor\Rules\Security\NoRawSqlInterpolation;

final class RuleRegistry
{
    /**
     * @return Rule[]
     */
    public static function all(): array
    {
        return [
            new NoEnvOutsideConfig(),
            new NoMassAssignmentGuardedEmpty(),
            new NoRawSqlInterpolation(),
            new PreferExistsOverCount(),
            new NoQueryInLoop(),
            new NoAllThenFilter(),
            new NoSaveInLoopWithoutTransaction(),
            new NoFatControllerMethod(),
            new PreferFormRequestValidation(),
            new NoBusinessLogicInRouteClosure(),
        ];
    }
}
```

- [ ] **Step 4: Correr toda la suite**

Run: `vendor/bin/phpunit`
Expected: PASS — todos los tests verdes (los del v1 más los nuevos).

- [ ] **Step 5: Verificación manual del binario**

Run:
```bash
cd /tmp && rm -rf ld-2a && mkdir -p ld-2a/app/Models && printf "<?php\nclass User { protected \$guarded = []; }\n" > ld-2a/app/Models/User.php && php /var/www/html/laravel-doctor/bin/laravel-doctor inspect ld-2a; echo "EXIT=$?"; rm -rf /tmp/ld-2a
```
Expected: lista `no-mass-assignment-guarded-empty` con severidad ERROR y exit 1.

- [ ] **Step 6: Commit**

```bash
git add src/Rules/RuleRegistry.php tests/Rules/RuleRegistryTest.php
git commit -m "feat: registrar las 6 reglas del plan 2a"
```

---

## Self-Review

**Cobertura del spec:**
- `no-mass-assignment-guarded-empty` → Task 1. ✓
- `no-raw-sql-interpolation` → Task 2. ✓
- `no-query-in-loop` → Task 3. ✓
- `no-all-then-filter` → Task 4. ✓
- `no-fat-controller-method` → Task 5. ✓
- `no-business-logic-in-route-closure` → Task 6. ✓
- Registro (4 → 10 reglas) + test ampliado → Task 7. ✓
- Reglas Eloquent difíciles movidas a boot → fuera de alcance, correcto (no hay tarea, intencional).
- Sin cambios en motor/pipeline/score/reporters → ninguna tarea los toca. ✓

**Placeholders:** ninguno; todo el código está completo. Los umbrales son constantes con
valor concreto (`MAX_METHOD_LINES = 40`, `MAX_CLOSURE_STMTS = 5`).

**Consistencia de tipos:** todas las reglas implementan la misma interfaz `Rule` con las
firmas del v1; usan `Categories`/`Severity` existentes; los tests usan `analyze()` (evita el
choque con `TestCase::run()`); nombres de nodos nikic v5 verificados (`Property`/`PropertyItem`
vía `->props`, `Array_->items`, `InterpolatedString`, `BinaryOp\Concat`, `ClassMethod`,
`Closure->stmts`, `StaticCall->class` como `Name` con `getLast()`). Los 10 ids del test de
registro coinciden exactamente con los `id()` definidos en las reglas del v1 y de este plan.
