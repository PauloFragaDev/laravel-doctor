<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

final class BladeScanner
{
    private const PATTERNS = [
        [BladeConstructKind::RawEcho, '/\{!!(.+?)!!\}/s'],
        [BladeConstructKind::EscapedEcho, '/\{\{(.+?)\}\}/s'],
        [BladeConstructKind::PhpBlock, '/@php\b(.*?)@endphp/s'],
    ];

    /**
     * @return BladeConstruct[]
     */
    public function scan(string $source): array
    {
        // Enmascara los comentarios {{-- --}} reemplazando cada carácter no-\n por un
        // espacio: así los offsets y los números de línea del resto se preservan.
        $masked = preg_replace_callback(
            '/\{\{--.*?--\}\}/s',
            static fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]),
            $source,
        );

        $constructs = [];
        foreach (self::PATTERNS as [$kind, $pattern]) {
            if (preg_match_all($pattern, $masked, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $offset = $match[0][1];
                    $line = substr_count(substr($masked, 0, $offset), "\n") + 1;
                    $constructs[] = new BladeConstruct($kind, trim($match[1][0]), $line);
                }
            }
        }

        return $constructs;
    }
}
