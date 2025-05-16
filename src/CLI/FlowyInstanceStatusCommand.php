<?php

declare(strict_types=1);

namespace Flowy\CLI;

use Flowy\Engine\WorkflowEngineInterface;
use Flowy\Model\WorkflowInstanceIdInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the status of a workflow instance.
 */
class FlowyInstanceStatusCommand extends Command
{
    protected static $defaultDescription = 'Show the status of a workflow instance.';

    private WorkflowEngineInterface $engine;

    private $instanceIdFactory;

    public function __construct(WorkflowEngineInterface $engine, callable $instanceIdFactory = null)
    {
        parent::__construct();
        $this->engine = $engine;
        $this->instanceIdFactory = $instanceIdFactory ?? [\Flowy\Model\WorkflowInstanceIdInterface::class, 'fromString'];
    }

    public static function getDefaultName(): ?string
    {
        return 'flowy:instance:status';
    }

    protected function configure(): void
    {
        $this->addArgument('instance_id', InputArgument::REQUIRED, 'Workflow instance ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $instanceId = $input->getArgument('instance_id');
        try {
            $instanceId = ($this->instanceIdFactory)($instanceId);
            $instance = $this->engine->getInstance($instanceId);
        } catch (\Throwable $e) {
            $io->error('Workflow instance not found: ' . $e->getMessage());
            return Command::FAILURE;
        }
        if ($instance === null) {
            $io->error('Workflow instance not found.');
            return Command::FAILURE;
        }
        $io->section('Workflow Instance Status');
        $io->listing([
            'Instance ID: ' . $instance->id->toString(),
            'Workflow ID: ' . $instance->definitionId,
            'Version: ' . $instance->definitionVersion,
            'Status: ' . $instance->status->name,
            'Business Key: ' . ($instance->businessKey ?? '-'),
            'Current Step: ' . ($instance->currentStepId ?? '-')
        ]);
        return Command::SUCCESS;
    }
}
