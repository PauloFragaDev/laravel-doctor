<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

final class ManifestExtractor
{
    /** @var callable(string):array{0:int,1:string} */
    private $runner;

    private ManifestParser $parser;

    /**
     * @param (callable(string):array{0:int,1:string})|null $runner
     *        Recibe el directorio del proyecto y devuelve [exitCode, stdout].
     *        Por defecto ejecuta `php artisan laravel-doctor:manifest --json`.
     */
    public function __construct(?callable $runner = null, ?ManifestParser $parser = null)
    {
        $this->runner = $runner ?? self::defaultRunner();
        $this->parser = $parser ?? new ManifestParser();
    }

    /**
     * Extrae el manifiesto del proyecto. Devuelve null si el comando falla
     * (la app no bootea, falta el comando, etc.) o si el JSON no es válido.
     */
    public function extract(string $projectDir): ?RuntimeManifest
    {
        [$exitCode, $stdout] = ($this->runner)($projectDir);
        if ($exitCode !== 0) {
            return null;
        }

        return $this->parser->parse($stdout);
    }

    /**
     * @return callable(string):array{0:int,1:string}
     */
    private static function defaultRunner(): callable
    {
        return static function (string $projectDir): array {
            $cmd = sprintf(
                'cd %s && php artisan laravel-doctor:manifest --json 2>/dev/null',
                escapeshellarg($projectDir),
            );
            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            return [$exitCode, implode("\n", $output)];
        };
    }
}
