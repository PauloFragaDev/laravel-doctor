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
