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
