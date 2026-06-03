<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Tui\ProjectDiscovery;
use LaravelDoctor\Tui\TerminalApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tui', description: 'Terminal interactiva: elige un proyecto y explora sus hallazgos.')]
final class TuiCommand extends Command
{
    private ProjectDiscovery $discovery;

    private ?TerminalApp $app;

    public function __construct(?ProjectDiscovery $discovery = null, ?TerminalApp $app = null)
    {
        parent::__construct();
        $this->discovery = $discovery ?? new ProjectDiscovery();
        $this->app = $app;
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

        if (!$input->isInteractive() || !$this->isTty()) {
            $output->writeln('El comando tui requiere una terminal interactiva. Para CI o tuberías usa "inspect".');

            return Command::SUCCESS;
        }

        return ($this->app ?? new TerminalApp())->run($projects);
    }

    /** Hay una terminal interactiva (TTY) en STDIN (no una tubería/redirección). */
    private function isTty(): bool
    {
        return !defined('STDIN') || !function_exists('stream_isatty') || @stream_isatty(STDIN);
    }
}
