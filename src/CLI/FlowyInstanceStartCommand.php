<?php

declare(strict_types=1);

namespace Flowy\CLI;

use Flowy\Engine\WorkflowEngineInterface;
use Flowy\Context\WorkflowContext;
use Flowy\Exception\DefinitionNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Starts a new workflow instance.
 */
class FlowyInstanceStartCommand extends Command
{
    protected static $defaultDescription = 'Start a new workflow instance.';

    private WorkflowEngineInterface $engine;

    public function __construct(WorkflowEngineInterface $engine)
    {
        parent::__construct();
        $this->engine = $engine;
    }

    public static function getDefaultName(): ?string
    {
        return 'flowy:instance:start';
    }

    protected function configure(): void
    {
        $this->addArgument('workflow_id', InputArgument::REQUIRED, 'Workflow definition ID');
        $this->addArgument('version', InputArgument::OPTIONAL, 'Workflow definition version (optional, defaults to latest)');
        $this->addOption('business-key', null, InputOption::VALUE_OPTIONAL, 'Business key for the instance');
        $this->addOption('context', null, InputOption::VALUE_OPTIONAL, 'Initial context as JSON string');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workflowId = $input->getArgument('workflow_id');
        $version = $input->getArgument('version');
        $businessKey = $input->getOption('business-key');
        $contextJson = $input->getOption('context');
        $context = null;
        if ($contextJson !== null) {
            try {
                $data = json_decode($contextJson, true, 512, JSON_THROW_ON_ERROR);
                $context = new WorkflowContext($data);
            } catch (\Throwable $e) {
                $io->error('Invalid context JSON: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }
        try {
            $instance = $this->engine->start($workflowId, $version, $context, $businessKey);
        } catch (DefinitionNotFoundException $e) {
            $io->error('Workflow definition not found: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error('Failed to start workflow instance: ' . $e->getMessage());
            return Command::FAILURE;
        }
        $io->success('Workflow instance started successfully.');
        $io->listing([
            'Instance ID: ' . $instance->id->toString(),
            'Workflow ID: ' . $instance->definitionId,
            'Version: ' . $instance->definitionVersion,
            'Status: ' . $instance->status->name,
            'Business Key: ' . ($instance->businessKey ?? '-')
        ]);
        return Command::SUCCESS;
    }
}
