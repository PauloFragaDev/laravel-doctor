<?php

declare(strict_types=1);

namespace LaravelDoctor\Diagnostics;

final class Categories
{
    public const SECURITY = 'security';
    public const PERFORMANCE = 'performance';
    public const ELOQUENT = 'eloquent';
    public const ARCHITECTURE = 'architecture';

    private const WEIGHTS = [
        self::SECURITY => 4,
        self::PERFORMANCE => 3,
        self::ELOQUENT => 3,
        self::ARCHITECTURE => 2,
    ];

    public static function weight(string $category): int
    {
        return self::WEIGHTS[$category] ?? 1;
    }
}
