<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

use LaravelDoctor\Diagnostics\Diagnostic;

/**
 * Suprime hallazgos mediante comentarios en el propio código:
 *   // laravel-doctor-disable-line            (esta línea, todas las reglas)
 *   // laravel-doctor-disable-line rule-id    (esta línea, solo esa regla)
 *   // laravel-doctor-disable-next-line[ id]  (la línea siguiente)
 * Funciona con cualquier sintaxis de comentario (//, #, /* *​/, {{-- --}}) porque solo busca
 * el marcador como subcadena. Solo aplica a hallazgos con línea > 0 (PHP/Blade), no a los de
 * runtime (rutas/modelos).
 */
final class InlineSuppressions
{
    private const DISABLE_LINE = 'laravel-doctor-disable-line';
    private const DISABLE_NEXT_LINE = 'laravel-doctor-disable-next-line';

    /**
     * @param Diagnostic[] $diagnostics
     * @param array<string,string> $fileContents path => contenido
     * @return Diagnostic[]
     */
    public function filter(array $diagnostics, array $fileContents): array
    {
        $lineCache = [];

        return array_values(array_filter($diagnostics, function (Diagnostic $d) use ($fileContents, &$lineCache) {
            if ($d->line < 1 || !isset($fileContents[$d->file])) {
                return true;
            }

            $lines = $lineCache[$d->file] ??= explode("\n", $fileContents[$d->file]);

            $sameLine = $lines[$d->line - 1] ?? '';
            if ($this->suppresses($sameLine, self::DISABLE_LINE, $d->ruleId)) {
                return false;
            }

            $prevLine = $d->line >= 2 ? ($lines[$d->line - 2] ?? '') : '';
            if ($this->suppresses($prevLine, self::DISABLE_NEXT_LINE, $d->ruleId)) {
                return false;
            }

            return true;
        }));
    }

    private function suppresses(string $line, string $marker, string $ruleId): bool
    {
        $pos = strpos($line, $marker);
        if ($pos === false) {
            return false;
        }

        // Texto tras el marcador: si nombra reglas, debe incluir esta; si no, suprime todas.
        $rest = trim(substr($line, $pos + strlen($marker)));
        $rest = rtrim($rest, '*/}- ');
        if ($rest === '') {
            return true;
        }

        return str_contains($rest, $ruleId);
    }
}
