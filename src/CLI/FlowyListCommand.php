<?php

declare(strict_types=1);

namespace Flowy\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Placeholder command for Flowy CLI MVP.
 */
class FlowyListCommand extends Command
{
    protected static $defaultDescription = 'List available Flowy commands (placeholder)';

    public static function getDefaultName(): ?string
    {
        return 'flowy:list';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Flowy CLI is working. (MVP placeholder command)</info>');
        return Command::SUCCESS;
    }
}
