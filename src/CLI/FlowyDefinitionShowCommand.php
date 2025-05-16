<?php

declare(strict_types=1);

namespace Flowy\CLI;

use Flowy\Registry\DefinitionRegistryInterface;
use Flowy\Exception\DefinitionNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows details for a specific workflow definition.
 */
class FlowyDefinitionShowCommand extends Command
{
    protected static $defaultDescription = 'Show details for a workflow definition.';

    private DefinitionRegistryInterface $definitionRegistry;

    public function __construct(DefinitionRegistryInterface $definitionRegistry)
    {
        parent::__construct();
        $this->definitionRegistry = $definitionRegistry;
    }

    public static function getDefaultName(): ?string
    {
        return 'flowy:definition:show';
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Workflow definition ID');
        $this->addArgument('version', InputArgument::OPTIONAL, 'Workflow definition version (optional, defaults to latest)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');
        $version = $input->getArgument('version');
        try {
            $def = $this->definitionRegistry->getDefinition($id, $version);
        } catch (DefinitionNotFoundException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $io->section('Workflow Definition');
        $io->listing([
            'ID: ' . $def->id,
            'Name: ' . ($def->name ?? '-'),
            'Version: ' . $def->version,
            'Description: ' . ($def->description ?? '-')
        ]);
        $io->section('Steps');
        $rows = [];
        foreach ($def->steps as $step) {
            $rows[] = [
                $step->id,
                $step->name ?? '-',
                $step->type ?? '-',
                $step->isInitial ? 'yes' : '',
                $step->description ?? '-',
            ];
        }
        $io->table(['ID', 'Name', 'Type', 'Initial', 'Description'], $rows);
        return Command::SUCCESS;
    }
}
