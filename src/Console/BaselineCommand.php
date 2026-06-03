<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Config\BaselineStorage;
use LaravelDoctor\Runtime\ManifestExtractor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Genera/actualiza doctor.baseline.json con los hallazgos actuales, para que a partir de
 * entonces `inspect` solo reporte los NUEVOS (ideal en codebases legacy).
 */
#[AsCommand(name: 'baseline', description: 'Congela los hallazgos actuales en doctor.baseline.json (solo se reportarán los nuevos).')]
final class BaselineCommand extends Command
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
            ->addArgument('path', InputArgument::OPTIONAL, 'Directorio del proyecto', getcwd())
            ->addOption('boot', null, InputOption::VALUE_NONE, 'Incluir también los hallazgos de runtime (--boot)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');

        // Analizamos SIN aplicar la baseline para capturar todos los hallazgos actuales.
        $result = $this->inspector->inspect($path, (bool) $input->getOption('boot'), false);

        $storage = new BaselineStorage();
        $total = $storage->write($path, $result->diagnostics);

        $output->writeln(sprintf(
            'Línea base escrita en %s con %d hallazgo(s). A partir de ahora "inspect" solo mostrará los nuevos.',
            $storage->path($path),
            $total,
        ));

        return Command::SUCCESS;
    }
}
