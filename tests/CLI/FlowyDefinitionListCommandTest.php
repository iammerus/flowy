<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use Flowy\CLI\FlowyDefinitionListCommand;
use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Registry\InMemoryDefinitionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FlowyDefinitionListCommandTest extends TestCase
{
    public function testListCommandShowsDefinitions(): void
    {
        $registry = new InMemoryDefinitionRegistry();
        $step = new \Flowy\Model\Data\StepDefinition(
            'start',
            [],
            [],
            'Start',
            null,
            true,
            'action',
            null, // description
            null  // retryPolicy
        );
        $def = new WorkflowDefinition(
            'order_fulfillment',
            '1.0.0',
            'start',
            [$step], // Provide at least one step
            'Order Fulfillment',
            'Handles order processing.'
        );
        $registry->addDefinition($def);
        $command = new FlowyDefinitionListCommand($registry);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);
        $output = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('order_fulfillment', $output);
        $this->assertStringContainsString('Order Fulfillment', $output);
        $this->assertStringContainsString('1.0.0', $output);
    }

    public function testListCommandShowsWarningIfEmpty(): void
    {
        $registry = new InMemoryDefinitionRegistry();
        $command = new FlowyDefinitionListCommand($registry);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);
        $output = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No workflow definitions are registered', $output);
    }
}
