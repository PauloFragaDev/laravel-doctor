<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;

final class NoUnescapedBladeOutput implements BladeRule
{
    public function id(): string { return 'no-unescaped-blade-output'; }

    public function title(): string { return 'Salida Blade sin escapar'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Usa {{ }} (escapa solo) en vez de {!! !!}, o sanea el HTML con un purificador antes de imprimirlo sin escapar.';
    }

    public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
    {
        if ($construct->kind !== BladeConstructKind::RawEcho) {
            return;
        }
        if (!str_contains($construct->expression, '$')) {
            return;
        }

        $context->report($construct, 'Salida sin escapar de una variable ({!! !!}): XSS si el contenido viene del usuario.');
    }
}
