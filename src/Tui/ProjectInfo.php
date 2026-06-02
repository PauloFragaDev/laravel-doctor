<?php

declare(strict_types=1);

namespace LaravelDoctor\Tui;

final readonly class ProjectInfo
{
    public function __construct(
        public string $name,
        public string $path,
    ) {
    }
}
