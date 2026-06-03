<?php

declare(strict_types=1);

namespace LaravelDoctor\Fix;

use LaravelDoctor\Scanner\SourceType;

/**
 * Arreglo automático y seguro para una regla concreta. Recibe el contenido de un archivo y
 * devuelve el contenido corregido, o null si no aplica / no hay nada que cambiar.
 */
interface Fixer
{
    /** Id de la regla que arregla. */
    public function ruleId(): string;

    public function fix(string $contents, SourceType $type): ?string;
}
