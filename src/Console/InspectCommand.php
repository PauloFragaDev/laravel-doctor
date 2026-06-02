<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Blade\BladeEngine;
use LaravelDoctor\Blade\BladeRuleRegistry;
use LaravelDoctor\Diagnostics\Pipeline;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Engine\Engine;
use LaravelDoctor\Scanner\SourceType;
use LaravelDoctor\Reporting\AgentReporter;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Rules\RuleRegistry;
use LaravelDoctor\Scanner\FileScanner;
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
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Directorio a analizar', getcwd())
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emite el reporte como JSON (para agentes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');

        $files = (new FileScanner())->scan($path);

        $phpFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Php));
        $bladeFiles = array_values(array_filter($files, fn ($f) => $f->type === SourceType::Blade));

        $diagnostics = array_merge(
            (new Engine(RuleRegistry::all()))->inspect($phpFiles),
            (new BladeEngine(BladeRuleRegistry::all()))->inspect($bladeFiles),
        );
        $diagnostics = (new Pipeline())->process($diagnostics);
        $score = (new ScoreCalculator())->score($diagnostics);

        $report = $input->getOption('json')
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
