<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Blade\BladeRuleRegistry;
use LaravelDoctor\Diagnostics\Pipeline;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Reporting\AgentReporter;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Rules\RuleRegistry;
use LaravelDoctor\Runtime\ManifestEngine;
use LaravelDoctor\Runtime\ManifestExtractor;
use LaravelDoctor\Runtime\ManifestRuleRegistry;
use LaravelDoctor\Scanner\FileScanner;
use LaravelDoctor\Scanner\SourceType;
use LaravelDoctor\Score\ScoreCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'inspect', description: 'Audita un codebase Laravel y muestra un score con los hallazgos.')]
final class InspectCommand extends Command
{
    public function __construct(private ?ManifestExtractor $extractor = null)
    {
        parent::__construct();
        $this->extractor ??= new ManifestExtractor();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Directorio a analizar', getcwd())
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emite el reporte como JSON (para agentes)')
            ->addOption('boot', null, InputOption::VALUE_NONE, 'Arranca la app (php artisan) para analizar rutas/config de runtime');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $isJson = (bool) $input->getOption('json');

        $files = (new FileScanner())->scan($path);

        $phpFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Php));
        $bladeFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Blade));

        $diagnostics = array_merge(
            (new Engine(RuleRegistry::all()))->inspect($phpFiles),
            (new BladeEngine(BladeRuleRegistry::all()))->inspect($bladeFiles),
        );

        if ($input->getOption('boot')) {
            $manifest = $this->extractor->extract($path);
            if ($manifest !== null) {
                $diagnostics = array_merge(
                    $diagnostics,
                    (new ManifestEngine(ManifestRuleRegistry::all()))->inspect($manifest),
                );
            } elseif (!$isJson) {
                // En modo JSON no contaminamos la salida; en TTY avisamos de la degradación.
                $output->writeln('Aviso: no se pudo bootear la app (--boot); analizando solo estático.');
            }
        }

        $diagnostics = (new Pipeline())->process($diagnostics);
        $score = (new ScoreCalculator())->score($diagnostics);

        $report = $isJson
            ? (new AgentReporter())->report($score, $diagnostics)
            : (new TtyReporter())->report($score, $diagnostics);

        $output->write($report);

        foreach ($diagnostics as $d) {
            if ($d->severity === Severity::Error) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
