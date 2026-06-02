<?php

declare(strict_types=1);

namespace LaravelDoctor\Blade;

enum BladeConstructKind
{
    case RawEcho;       // {!! ... !!}
    case EscapedEcho;   // {{ ... }}
    case PhpBlock;      // @php ... @endphp
}
