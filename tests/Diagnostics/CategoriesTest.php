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
