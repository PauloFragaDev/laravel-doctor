<?php

declare(strict_types=1);

namespace LaravelDoctor\Fix;

use LaravelDoctor\Scanner\SourceFile;

/**
 * Aplica los fixers a los archivos y reescribe los que cambian. Devuelve las rutas modificadas.
 */
final class AutoFixer
{
    /** @var Fixer[] */
    private array $fixers;

    /**
     * @param Fixer[]|null $fixers
     */
    public function __construct(?array $fixers = null)
    {
        $this->fixers = $fixers ?? FixerRegistry::all();
    }

    /**
     * @param SourceFile[] $files
     * @return string[] rutas de los archivos modificados
     */
    public function fix(array $files): array
    {
        $changed = [];
        foreach ($files as $file) {
            $contents = $file->contents;
            $updated = $contents;
            foreach ($this->fixers as $fixer) {
                $result = $fixer->fix($updated, $file->type);
                if ($result !== null) {
                    $updated = $result;
                }
            }
            if ($updated !== $contents) {
                file_put_contents($file->path, $updated);
                $changed[] = $file->path;
            }
        }

        return $changed;
    }
}
