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
 * Cancels a workflow instance.
 */
class FlowyInstanceCancelCommand extends Command
{
    protected static $defaultDescription = 'Cancel a workflow instance.';

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
        return 'flowy:instance:cancel';
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
            $this->engine->cancel($instanceId);
        } catch (\Throwable $e) {
            $io->error('Failed to cancel workflow instance: ' . $e->getMessage());
            return Command::FAILURE;
        }
        $io->success('Workflow instance cancelled.');
        return Command::SUCCESS;
    }
}
