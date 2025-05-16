<?php

declare(strict_types=1);

namespace Flowy\CLI;

use Flowy\Registry\DefinitionRegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists all registered workflow definitions.
 */
class FlowyDefinitionListCommand extends Command
{
    protected static $defaultDescription = 'List all registered workflow definitions.';

    private DefinitionRegistryInterface $definitionRegistry;

    public function __construct(DefinitionRegistryInterface $definitionRegistry)
    {
        parent::__construct();
        $this->definitionRegistry = $definitionRegistry;
    }

    public static function getDefaultName(): ?string
    {
        return 'flowy:definition:list';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $definitions = $this->definitionRegistry->findDefinitions();
        if (empty($definitions)) {
            $io->warning('No workflow definitions are registered.');
            return Command::SUCCESS;
        }
        $rows = [];
        foreach ($definitions as $def) {
            $rows[] = [
                $def->id,
                $def->name ?? '-',
                $def->version,
            ];
        }
        $io->table(['ID', 'Name', 'Version'], $rows);
        return Command::SUCCESS;
    }
}
