<?php

declare(strict_types=1);

namespace LaravelDoctor\Web;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Tui\ProjectDiscovery;

/**
 * Provee los datos JSON del dashboard web. Sin estado HTTP: el router lo invoca y serializa.
 * Lee fragmentos de código de forma segura (solo dentro del directorio base).
 */
final class WebController
{
    private string $base;

    public function __construct(string $base, private ?Inspector $inspector = null)
    {
        $this->base = rtrim($base, '/');
        $this->inspector ??= new Inspector();
    }

    /**
     * @return array<int,array{name:string,path:string}>
     */
    public function projects(): array
    {
        return array_map(
            fn ($p) => ['name' => $p->name, 'path' => $p->path],
            (new ProjectDiscovery())->discover($this->base),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function inspect(string $projectPath, bool $boot): array
    {
        if (!$this->isWithinBase($projectPath)) {
            return ['error' => 'Proyecto fuera del directorio base.'];
        }

        $result = $this->inspector->inspect($projectPath, $boot);

        $diagnostics = array_map(fn (Diagnostic $d) => [
            'id' => $d->ruleId,
            'category' => $d->category,
            'severity' => $d->severity->value,
            'file' => $d->file,
            'line' => $d->line,
            'message' => $d->message,
            'recommendation' => $d->recommendation,
            'snippet' => $this->snippet($d->file, $d->line),
        ], $result->diagnostics);

        return [
            'score' => $result->score->score,
            'label' => $result->score->label,
            'bootFailed' => $result->bootFailed,
            'diagnostics' => $diagnostics,
        ];
    }

    public function snippet(string $file, int $line): ?string
    {
        if ($line < 1 || !$this->isWithinBase($file) || !is_file($file)) {
            return null;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false || !isset($lines[$line - 1])) {
            return null;
        }

        return trim($lines[$line - 1]);
    }

    private function isWithinBase(string $path): bool
    {
        $real = realpath($path);
        $baseReal = realpath($this->base);
        if ($real === false || $baseReal === false) {
            return false;
        }

        return $real === $baseReal || str_starts_with($real, $baseReal . '/');
    }
}
