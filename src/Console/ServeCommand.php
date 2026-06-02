<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Levanta el dashboard web con el servidor embebido de PHP y abre el navegador.
 * Integración-only (lanza un proceso bloqueante); la lógica de datos vive en WebController.
 */
#[AsCommand(name: 'serve', description: 'Abre el dashboard web para explorar los hallazgos de forma interactiva.')]
final class ServeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('base', null, InputOption::VALUE_REQUIRED, 'Directorio base donde buscar proyectos', getcwd())
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Puerto', '8420')
            ->addOption('no-open', null, InputOption::VALUE_NONE, 'No abrir el navegador automáticamente');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $base = (string) $input->getOption('base');
        $host = (string) $input->getOption('host');
        $port = (string) $input->getOption('port');
        $router = __DIR__ . '/../Web/router.php';
        $url = sprintf('http://%s:%s', $host, $port);

        $output->writeln(sprintf('laravel-doctor dashboard en <info>%s</info> (base: %s)', $url, $base));
        $output->writeln('Pulsa Ctrl+C para detener.');

        if (!$input->getOption('no-open')) {
            $this->openBrowser($url);
        }

        $command = sprintf(
            'LARAVEL_DOCTOR_BASE=%s php -S %s:%s %s',
            escapeshellarg($base),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($router),
        );

        passthru($command, $exitCode);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function openBrowser(string $url): void
    {
        $opener = match (true) {
            stripos(PHP_OS, 'DARWIN') === 0 => 'open',
            stripos(PHP_OS, 'WIN') === 0 => 'start',
            default => 'xdg-open',
        };

        // Best-effort: si no hay navegador/entorno gráfico, simplemente no pasa nada.
        @exec(sprintf('%s %s > /dev/null 2>&1 &', $opener, escapeshellarg($url)));
    }
}
