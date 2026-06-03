<?php

declare(strict_types=1);

namespace LaravelDoctor\Git;

/**
 * Lista los archivos cambiados en git (rutas absolutas), para el análisis incremental. El
 * comando git se ejecuta vía un runner inyectable (testeable). Devuelve null si git no está
 * disponible o falla (el llamador puede entonces caer a analizar todo).
 */
final class GitChangedFiles
{
    /** @var callable(string,string):array{0:int,1:string} */
    private $runner;

    /**
     * @param (callable(string,string):array{0:int,1:string})|null $runner recibe (dir, args git)
     */
    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? self::defaultRunner();
    }

    /** @return string[]|null rutas absolutas de archivos cambiados respecto a $ref */
    public function since(string $dir, string $ref): ?array
    {
        return $this->run($dir, 'diff --name-only --diff-filter=ACMR ' . escapeshellarg($ref));
    }

    /** @return string[]|null rutas absolutas de archivos en el staging area */
    public function staged(string $dir): ?array
    {
        return $this->run($dir, 'diff --name-only --cached --diff-filter=ACMR');
    }

    /** @return string[]|null */
    private function run(string $dir, string $args): ?array
    {
        [$exitCode, $stdout] = ($this->runner)($dir, $args);
        if ($exitCode !== 0) {
            return null;
        }

        $base = rtrim($dir, '/');
        $files = [];
        foreach (explode("\n", trim($stdout)) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $files[] = $base . '/' . $line;
            }
        }

        return $files;
    }

    /**
     * @return callable(string,string):array{0:int,1:string}
     */
    private static function defaultRunner(): callable
    {
        return static function (string $dir, string $args): array {
            $cmd = sprintf('git -C %s %s 2>/dev/null', escapeshellarg($dir), $args);
            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            return [$exitCode, implode("\n", $output)];
        };
    }
}
