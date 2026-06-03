<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Reporting\AgentReporter;
use LaravelDoctor\Reporting\GithubReporter;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Runtime\ManifestExtractor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'inspect', description: 'Audita un codebase Laravel y muestra un score con los hallazgos.')]
final class InspectCommand extends Command
{
    private Inspector $inspector;

    public function __construct(?ManifestExtractor $extractor = null)
    {
        parent::__construct();
        $this->inspector = new Inspector($extractor);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Directorio a analizar', getcwd())
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emite el reporte como JSON (para agentes)')
            ->addOption('github', null, InputOption::VALUE_NONE, 'Emite anotaciones de GitHub Actions (inline en el PR)')
            ->addOption('boot', null, InputOption::VALUE_NONE, 'Arranca la app (php artisan) para analizar rutas/config de runtime')
            ->addOption('no-baseline', null, InputOption::VALUE_NONE, 'Ignora doctor.baseline.json y muestra todos los hallazgos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $isJson = (bool) $input->getOption('json');

        $result = $this->inspector->inspect(
            $path,
            (bool) $input->getOption('boot'),
            !$input->getOption('no-baseline'),
        );

        if ($result->bootFailed && !$isJson) {
            // En modo JSON no contaminamos la salida; en TTY avisamos de la degradación.
            $output->writeln('Aviso: no se pudo bootear la app (--boot); analizando solo estático.');
        }

        $report = match (true) {
            (bool) $input->getOption('github') => (new GithubReporter())->report($result->score, $result->diagnostics),
            $isJson => (new AgentReporter())->report($result->score, $result->diagnostics),
            default => (new TtyReporter())->report($result->score, $result->diagnostics),
        };

        $output->write($report);

        return $result->hasError() ? Command::FAILURE : Command::SUCCESS;
    }
}
