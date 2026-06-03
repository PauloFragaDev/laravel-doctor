<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Git;

use LaravelDoctor\Git\GitChangedFiles;
use PHPUnit\Framework\TestCase;

final class GitChangedFilesTest extends TestCase
{
    public function test_since_returns_absolute_paths(): void
    {
        $git = new GitChangedFiles(fn (string $dir, string $args) => [0, "app/Pay.php\napp/User.php\n"]);
        $files = $git->since('/proj', 'main');

        $this->assertSame(['/proj/app/Pay.php', '/proj/app/User.php'], $files);
    }

    public function test_staged_returns_paths(): void
    {
        $git = new GitChangedFiles(fn (string $dir, string $args) => [0, "app/X.php"]);
        $this->assertSame(['/proj/app/X.php'], $git->staged('/proj'));
    }

    public function test_returns_null_when_git_fails(): void
    {
        $git = new GitChangedFiles(fn (string $dir, string $args) => [128, '']);
        $this->assertNull($git->since('/proj', 'main'));
    }

    public function test_empty_diff_returns_empty_array(): void
    {
        $git = new GitChangedFiles(fn (string $dir, string $args) => [0, '']);
        $this->assertSame([], $git->since('/proj', 'main'));
    }
}
