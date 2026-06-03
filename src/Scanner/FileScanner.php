<?php

declare(strict_types=1);

namespace LaravelDoctor\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileScanner
{
    /** Nombres de directorio que se ignoran a cualquier profundidad (vendor anidado, etc.). */
    private const EXCLUDED_NAMES = [
        'vendor', 'node_modules', 'storage',
    ];

    /** Rutas relativas a la raíz del proyecto que se ignoran. */
    private const EXCLUDED_RELATIVE = [
        'bootstrap/cache', 'public', 'tests',
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
        $segments = explode('/', $relative);
        $dirSegments = array_slice($segments, 0, -1);

        foreach ($dirSegments as $segment) {
            // Directorios ocultos (.git, .history de VS Code, .idea, .vscode...) nunca son fuente.
            if ($segment !== '' && $segment[0] === '.') {
                return true;
            }
            if (in_array($segment, self::EXCLUDED_NAMES, true)) {
                return true;
            }
        }

        foreach (self::EXCLUDED_RELATIVE as $dir) {
            if ($relative === $dir || str_starts_with($relative, $dir . '/')) {
                return true;
            }
        }

        return false;
    }
}
