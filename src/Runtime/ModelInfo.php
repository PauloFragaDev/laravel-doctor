<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

final readonly class ModelInfo
{
    /**
     * @param string[] $casts Claves de atributos con cast declarado en el modelo.
     * @param array<string,string> $columns Columnas de la DB: nombre => tipo.
     */
    public function __construct(
        public string $class,
        public string $table,
        public array $casts,
        public array $columns,
    ) {
    }
}
