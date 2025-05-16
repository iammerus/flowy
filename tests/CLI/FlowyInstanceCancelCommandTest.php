<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use Flowy\CLI\FlowyInstanceCancelCommand;
use Flowy\Engine\WorkflowEngineInterface;
use Flowy\Model\WorkflowInstanceIdInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Flowy\Tests\CLI\DummyWorkflowInstanceId;

class FlowyInstanceCancelCommandTest extends TestCase
{
    public function testCancelSuccess(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('cancel')
            ->with($this->isInstanceOf(WorkflowInstanceIdInterface::class));
        $command = new FlowyInstanceCancelCommand($engine, [DummyWorkflowInstanceId::class, 'fromString']);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'instance_id' => 'dummy-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Workflow instance cancelled', $output);
    }

    public function testCancelThrowsException(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('cancel')->willThrowException(new \RuntimeException('error'));
        $command = new FlowyInstanceCancelCommand($engine, [DummyWorkflowInstanceId::class, 'fromString']);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'instance_id' => 'dummy-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to cancel workflow instance', $output);
    }
}
