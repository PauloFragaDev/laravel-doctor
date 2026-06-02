<?php

declare(strict_types=1);

namespace LaravelDoctor\Scanner;

final readonly class SourceFile
{
    public function __construct(
        public string $path,
        public string $contents,
        public SourceType $type = SourceType::Php,
    ) {
    }
}
