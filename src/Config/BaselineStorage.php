<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

use LaravelDoctor\Diagnostics\Diagnostic;

/**
 * Lee y escribe el fichero de línea base (doctor.baseline.json) en la raíz del proyecto.
 * El formato es estable y ordenado (apto para git).
 */
final class BaselineStorage
{
    public const FILE = 'doctor.baseline.json';

    public function path(string $projectDir): string
    {
        return rtrim($projectDir, '/') . '/' . self::FILE;
    }

    public function load(string $projectDir): ?Baseline
    {
        $path = $this->path($projectDir);
        if (!is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        $data = $contents === false ? null : json_decode($contents, true);
        if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
            return null;
        }

        $counts = [];
        foreach ($data['entries'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = Baseline::key((string) ($entry['rule'] ?? ''), (string) ($entry['file'] ?? ''));
            $counts[$key] = (int) ($entry['count'] ?? 0);
        }

        return new Baseline($counts);
    }

    /**
     * Construye y escribe la línea base a partir de los hallazgos actuales. Devuelve el total.
     *
     * @param Diagnostic[] $diagnostics
     */
    public function write(string $projectDir, array $diagnostics): int
    {
        $counts = [];
        foreach ($diagnostics as $d) {
            $key = Baseline::key($d->ruleId, $this->relative($d->file, $projectDir));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        ksort($counts);

        $entries = [];
        foreach ($counts as $key => $count) {
            [$rule, $file] = explode(Baseline::KEY_SEPARATOR, $key, 2);
            $entries[] = ['rule' => $rule, 'file' => $file, 'count' => $count];
        }

        file_put_contents(
            $this->path($projectDir),
            json_encode(['version' => 1, 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );

        return array_sum($counts);
    }

    private function relative(string $file, string $projectDir): string
    {
        $base = rtrim($projectDir, '/') . '/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
