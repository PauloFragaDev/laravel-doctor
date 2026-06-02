<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Tui\ProjectDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[AsCommand(name: 'tui', description: 'Terminal interactiva: elige un proyecto Laravel y audítalo desde un menú.')]
final class TuiCommand extends Command
{
    private const ACTION_STATIC = 'Auditar (estático)';
    private const ACTION_BOOT = 'Auditar (con --boot)';
    private const ACTION_BACK = 'Volver';
    private const EXIT = 'Salir';

    private ProjectDiscovery $discovery;

    private Inspector $inspector;

    public function __construct(?ProjectDiscovery $discovery = null, ?Inspector $inspector = null)
    {
        parent::__construct();
        $this->discovery = $discovery ?? new ProjectDiscovery();
        $this->inspector = $inspector ?? new Inspector();
    }

    protected function configure(): void
    {
        $this->addOption('base', null, InputOption::VALUE_REQUIRED, 'Directorio base donde buscar proyectos', getcwd());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $base = (string) $input->getOption('base');
        $projects = $this->discovery->discover($base);

        if ($projects === []) {
            $output->writeln(sprintf('No se encontraron proyectos Laravel en %s.', $base));

            return Command::SUCCESS;
        }

        if (!$input->isInteractive()) {
            $output->writeln('El comando tui requiere una terminal interactiva.');

            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        $byName = [];
        foreach ($projects as $project) {
            $byName[$project->name] = $project;
        }

        while (true) {
            $choice = $helper->ask(
                $input,
                $output,
                new ChoiceQuestion('Elige un proyecto:', [...array_keys($byName), self::EXIT]),
            );
            if ($choice === self::EXIT) {
                return Command::SUCCESS;
            }

            $action = $helper->ask(
                $input,
                $output,
                new ChoiceQuestion('Acción:', [self::ACTION_STATIC, self::ACTION_BOOT, self::ACTION_BACK]),
            );
            if ($action === self::ACTION_BACK) {
                continue;
            }

            $result = $this->inspector->inspect($byName[$choice]->path, $action === self::ACTION_BOOT);
            if ($result->bootFailed) {
                $output->writeln('Aviso: no se pudo bootear la app; analizando solo estático.');
            }
            $output->write((new TtyReporter())->report($result->score, $result->diagnostics));
        }
    }
}
