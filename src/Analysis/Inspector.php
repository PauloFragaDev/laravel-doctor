<?php

declare(strict_types=1);

namespace LaravelDoctor\Analysis;

use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Blade\BladeRuleRegistry;
use LaravelDoctor\Config\BaselineApplier;
use LaravelDoctor\Config\BaselineStorage;
use LaravelDoctor\Config\ConfigApplier;
use LaravelDoctor\Config\ConfigLoader;
use LaravelDoctor\Config\InlineSuppressions;
use LaravelDoctor\Diagnostics\Pipeline;
use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Rules\RuleRegistry;
use LaravelDoctor\Runtime\ManifestEngine;
use LaravelDoctor\Runtime\ManifestExtractor;
use LaravelDoctor\Runtime\ManifestRuleRegistry;
use LaravelDoctor\Scanner\FileScanner;
use LaravelDoctor\Scanner\SourceType;
use LaravelDoctor\Score\ScoreCalculator;

/**
 * Orquesta una auditoría completa de un directorio: descubre archivos, corre los motores
 * (PHP + Blade, y el de manifiesto si se pide boot), aplica el pipeline y calcula el score.
 * Lo comparten InspectCommand y TuiCommand.
 */
final class Inspector
{
    private ManifestExtractor $extractor;

    public function __construct(?ManifestExtractor $extractor = null)
    {
        $this->extractor = $extractor ?? new ManifestExtractor();
    }

    public function inspect(string $path, bool $boot = false, bool $useBaseline = true): InspectionResult
    {
        $files = (new FileScanner())->scan($path);
        $phpFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Php));
        $bladeFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Blade));

        $diagnostics = array_merge(
            (new Engine(RuleRegistry::all()))->inspect($phpFiles),
            (new BladeEngine(BladeRuleRegistry::all()))->inspect($bladeFiles),
        );

        $bootFailed = false;
        if ($boot) {
            $manifest = $this->extractor->extract($path);
            if ($manifest !== null) {
                $diagnostics = array_merge(
                    $diagnostics,
                    (new ManifestEngine(ManifestRuleRegistry::all()))->inspect($manifest),
                );
            } else {
                $bootFailed = true;
            }
        }

        $config = (new ConfigLoader())->load($path);
        $diagnostics = (new ConfigApplier())->apply($diagnostics, $config);

        $fileContents = [];
        foreach ($files as $file) {
            $fileContents[$file->path] = $file->contents;
        }
        $diagnostics = (new InlineSuppressions())->filter($diagnostics, $fileContents);

        if ($useBaseline) {
            $baseline = (new BaselineStorage())->load($path);
            if ($baseline !== null) {
                $diagnostics = (new BaselineApplier())->apply($diagnostics, $baseline, $path);
            }
        }

        $diagnostics = (new Pipeline())->process($diagnostics);
        $score = (new ScoreCalculator())->score($diagnostics);

        return new InspectionResult($score, $diagnostics, $bootFailed);
    }
}
