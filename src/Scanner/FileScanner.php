<?php

declare(strict_types=1);

namespace LaravelDoctor\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileScanner
{
    private const EXCLUDED_DIRS = [
        'vendor', 'node_modules', 'storage', '.git', 'bootstrap/cache', 'public', 'tests',
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
            $type = str_ends_with($path, '.blade.php') ? SourceType::Blade : SourceType::Php;
            $files[] = new SourceFile($path, $contents, $type);
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
