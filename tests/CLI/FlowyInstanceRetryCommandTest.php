<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use Flowy\CLI\FlowyInstanceRetryCommand;
use Flowy\Engine\WorkflowEngineInterface;
use Flowy\Model\WorkflowInstanceIdInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Flowy\Tests\CLI\DummyWorkflowInstanceId;

class FlowyInstanceRetryCommandTest extends TestCase
{
    public function testRetrySuccess(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('retryFailedStep')
            ->with($this->isInstanceOf(WorkflowInstanceIdInterface::class));
        $command = new FlowyInstanceRetryCommand($engine, [DummyWorkflowInstanceId::class, 'fromString']);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'instance_id' => 'dummy-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Workflow instance retry initiated', $output);
    }

    public function testRetryThrowsException(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('retryFailedStep')->willThrowException(new \RuntimeException('error'));
        $command = new FlowyInstanceRetryCommand($engine, [DummyWorkflowInstanceId::class, 'fromString']);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'instance_id' => 'dummy-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to retry workflow instance', $output);
    }
}
