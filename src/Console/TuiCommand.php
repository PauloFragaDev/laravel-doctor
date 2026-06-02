<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Analysis\Inspector;
use LaravelDoctor\Blade\BladeRuleRegistry;
use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Reporting\TtyReporter;
use LaravelDoctor\Rules\RuleRegistry;
use LaravelDoctor\Runtime\ManifestRuleRegistry;
use LaravelDoctor\Tui\ProjectDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'tui', description: 'Terminal interactiva: elige un proyecto Laravel y audítalo (global o algo específico).')]
final class TuiCommand extends Command
{
    private const GLOBAL_STATIC = 'Auditoría global (estático)';
    private const GLOBAL_BOOT = 'Auditoría global (con --boot)';
    private const BY_CATEGORY = 'Por categoría';
    private const BY_RULE = 'Por regla concreta';
    private const BACK = 'Volver';
    private const EXIT = 'Salir';

    private const CATEGORIES = [
        Categories::SECURITY,
        Categories::PERFORMANCE,
        Categories::ELOQUENT,
        Categories::ARCHITECTURE,
    ];

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

        $io = new SymfonyStyle($input, $output);
        $byName = [];
        foreach ($projects as $project) {
            $byName[$project->name] = $project;
        }

        while (true) {
            $choice = $io->choice('Elige un proyecto', [...array_keys($byName), self::EXIT]);
            if ($choice === self::EXIT) {
                return Command::SUCCESS;
            }

            $this->projectMenu($io, $byName[$choice]->path);
        }
    }

    private function projectMenu(SymfonyStyle $io, string $path): void
    {
        while (true) {
            $action = $io->choice('Acción', [
                self::GLOBAL_STATIC,
                self::GLOBAL_BOOT,
                self::BY_CATEGORY,
                self::BY_RULE,
                self::BACK,
            ]);

            switch ($action) {
                case self::BACK:
                    return;
                case self::GLOBAL_STATIC:
                    $this->runAndRender($io, $path, false, null, null);
                    break;
                case self::GLOBAL_BOOT:
                    $this->runAndRender($io, $path, true, null, null);
                    break;
                case self::BY_CATEGORY:
                    $category = $io->choice('Categoría', [...self::CATEGORIES, self::BACK]);
                    if ($category !== self::BACK) {
                        $this->runAndRender($io, $path, false, $category, null);
                    }
                    break;
                case self::BY_RULE:
                    $rule = $io->choice('Regla', [...$this->allRuleIds(), self::BACK]);
                    if ($rule !== self::BACK) {
                        $this->runAndRender($io, $path, false, null, $rule);
                    }
                    break;
            }
        }
    }

    private function runAndRender(SymfonyStyle $io, string $path, bool $boot, ?string $category, ?string $rule): void
    {
        $result = $this->inspector->inspect($path, $boot);
        if ($result->bootFailed) {
            $io->warning('No se pudo bootear la app; analizando solo estático.');
        }

        $diagnostics = $result->diagnostics;
        if ($category !== null) {
            $diagnostics = array_values(array_filter($diagnostics, fn (Diagnostic $d) => $d->category === $category));
        }
        if ($rule !== null) {
            $diagnostics = array_values(array_filter($diagnostics, fn (Diagnostic $d) => $d->ruleId === $rule));
        }

        $io->write((new TtyReporter())->report($result->score, $diagnostics));
    }

    /**
     * @return string[]
     */
    private function allRuleIds(): array
    {
        $ids = [];
        foreach ([...RuleRegistry::all(), ...BladeRuleRegistry::all(), ...ManifestRuleRegistry::all()] as $rule) {
            $ids[] = $rule->id();
        }
        sort($ids);

        return array_values(array_unique($ids));
    }
}
