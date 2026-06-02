<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Tui\InteractiveSession;
use LaravelDoctor\Tui\ProjectDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tui', description: 'Terminal interactiva: elige un proyecto y explora sus hallazgos.')]
final class TuiCommand extends Command
{
    private ProjectDiscovery $discovery;

    private ?InteractiveSession $session;

    public function __construct(?ProjectDiscovery $discovery = null, ?InteractiveSession $session = null)
    {
        parent::__construct();
        $this->discovery = $discovery ?? new ProjectDiscovery();
        $this->session = $session;
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

        return ($this->session ?? new InteractiveSession())->run($projects);
    }
}
