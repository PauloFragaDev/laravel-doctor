<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'install', description: 'Instala la skill de laravel-doctor para tu agente de IA.')]
final class InstallCommand extends Command
{
    private const SKILL_SOURCE = __DIR__ . '/../../skills/laravel-doctor/SKILL.md';

    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::OPTIONAL, 'Directorio del proyecto', getcwd());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = rtrim((string) $input->getArgument('target'), '/');
        $destDir = $target . '/.claude/skills/laravel-doctor';

        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            $output->writeln('<error>No se pudo crear el directorio de la skill.</error>');

            return Command::FAILURE;
        }

        $contents = file_get_contents(self::SKILL_SOURCE);
        if ($contents === false) {
            $output->writeln('<error>No se encontró la skill de origen.</error>');

            return Command::FAILURE;
        }

        file_put_contents($destDir . '/SKILL.md', $contents);
        $output->writeln('Skill instalada en ' . $destDir . '/SKILL.md');

        return Command::SUCCESS;
    }
}
