<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

/**
 * Conjunto de hallazgos "aceptados" (línea base): cuántas veces se tolera cada combinación
 * (regla, archivo relativo). No guarda la línea, para que aguante el desplazamiento del código.
 */
final readonly class Baseline
{
    public const KEY_SEPARATOR = "\0";

    /**
     * @param array<string,int> $counts clave = "<regla>\0<archivo relativo>" => número tolerado
     */
    public function __construct(public array $counts)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->counts === [];
    }

    public function total(): int
    {
        return array_sum($this->counts);
    }

    public static function key(string $ruleId, string $relativeFile): string
    {
        return $ruleId . self::KEY_SEPARATOR . $relativeFile;
    }
}
