<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use Flowy\CLI\FlowyDefinitionShowCommand;
use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Registry\InMemoryDefinitionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FlowyDefinitionShowCommandTest extends TestCase
{
    public function testShowCommandDisplaysDefinitionDetails(): void
    {
        $registry = new InMemoryDefinitionRegistry();
        $step = new StepDefinition(
            'start',
            [],
            [],
            'Start',                // name
            'The initial step.',    // description
            true,                   // isInitial
            'action',               // type
            null,                   // retryPolicy
            null                    // timeoutDuration
        );
        $def = new WorkflowDefinition(
            'order_fulfillment',
            '1.0.0',
            'start',
            [$step],
            'Order Fulfillment',
            'Handles order processing.'
        );
        $registry->addDefinition($def);
        $command = new FlowyDefinitionShowCommand($registry);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['id' => 'order_fulfillment', 'version' => '1.0.0']);
        $output = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Order Fulfillment', $output);
        $this->assertStringContainsString('Handles order processing.', $output);
        $this->assertStringContainsString('start', $output);
        $this->assertStringContainsString('The initial step.', $output);
    }

    public function testShowCommandDisplaysErrorIfNotFound(): void
    {
        $registry = new InMemoryDefinitionRegistry();
        $command = new FlowyDefinitionShowCommand($registry);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['id' => 'not_found']);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No workflow definitions found for ID', $output);
    }
}
