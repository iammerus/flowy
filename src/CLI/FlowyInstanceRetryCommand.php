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
 * Retries a failed workflow instance.
 */
class FlowyInstanceRetryCommand extends Command
{
    protected static $defaultDescription = 'Retry a failed workflow instance.';

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
        return 'flowy:instance:retry';
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
            $this->engine->retryFailedStep($instanceId);
        } catch (\Throwable $e) {
            $io->error('Failed to retry workflow instance: ' . $e->getMessage());
            return Command::FAILURE;
        }
        $io->success('Workflow instance retry initiated.');
        return Command::SUCCESS;
    }
}
