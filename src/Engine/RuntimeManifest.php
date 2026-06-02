<?php

declare(strict_types=1);

namespace LaravelDoctor\Engine;

/**
 * Datos resueltos en runtime (rutas, modelos, config, middleware) que el motor
 * podrá cruzar con el AST a partir de v1.1, cuando se implemente el arnés de boot.
 *
 * En v1 es un hueco de extensión: Engine::inspect() lo acepta pero siempre recibe
 * null. Existe como interfaz —en vez de un array suelto— para que ampliar el motor
 * en v1.1 no cambie la firma pública.
 */
interface RuntimeManifest
{
}
