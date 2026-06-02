<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Blade;

use LaravelDoctor\Blade\BladeConstruct;
use LaravelDoctor\Blade\BladeConstructKind;
use LaravelDoctor\Blade\BladeRule;
use LaravelDoctor\Blade\BladeRuleContext;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;

final class NoLogicInBlade implements BladeRule
{
    private const QUERY_TOKENS = [
        '->get(', '->all(', '->first(', '->where(', '->count(', '->save(', '::all(', '::where(', 'DB::',
    ];

    public function id(): string { return 'no-logic-in-blade'; }

    public function title(): string { return 'Lógica o consultas en la plantilla Blade'; }

    public function category(): string { return Categories::ARCHITECTURE; }

    public function severity(): Severity { return Severity::Warning; }

    public function recommendation(): string
    {
        return 'Mueve los datos y la lógica al controller o a un view composer; la vista debería recibir solo lo ya resuelto.';
    }

    public function enterConstruct(BladeConstruct $construct, BladeRuleContext $context): void
    {
        if ($construct->kind === BladeConstructKind::PhpBlock) {
            $context->report($construct, 'Bloque @php en la vista: la lógica no debería vivir en la plantilla.');

            return;
        }

        foreach (self::QUERY_TOKENS as $token) {
            if (str_contains($construct->expression, $token)) {
                $context->report($construct, 'Consulta a la base de datos dentro de la vista: acopla presentación y negocio.');

                return;
            }
        }
    }
}
