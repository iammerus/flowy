<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use Flowy\CLI\FlowyInstanceStatusCommand;
use Flowy\Engine\WorkflowEngineInterface;
use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Flowy\Tests\CLI\DummyWorkflowInstanceId;

class FlowyInstanceStatusCommandTest extends TestCase
{
    public function testStatusInstanceSuccess(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $instance = new WorkflowInstance(
            new DummyWorkflowInstanceId(),
            'wf-id',
            '1.0',
            WorkflowStatus::RUNNING,
            new WorkflowContext(['foo' => 'bar']),
            new \DateTimeImmutable('2024-01-01T12:00:00+00:00'),
            new \DateTimeImmutable('2024-01-02T12:00:00+00:00'),
            'biz-key',
            'step1',
            [],
            null,
            0,
            1
        );
        $engine->expects($this->once())
            ->method('getInstance')
            ->with($this->isInstanceOf(WorkflowInstanceIdInterface::class))
            ->willReturn($instance);
        $command = new FlowyInstanceStatusCommand($engine, [DummyWorkflowInstanceId::class, 'fromString']);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'instance_id' => 'dummy-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Instance ID: dummy-id', $output);
        $this->assertStringContainsString('Workflow ID: wf-id', $output);
        $this->assertStringContainsString('Status: RUNNING', $output);
        $this->assertStringContainsString('Business Key: biz-key', $output);
        $this->assertStringContainsString('Current Step: step1', $output);
    }

    public function testStatusInstanceNotFound(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('getInstance')->willReturn(null);
        $command = new FlowyInstanceStatusCommand($engine, [DummyWorkflowInstanceId::class, 'fromString']);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'instance_id' => 'not-exist'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Workflow instance not found', $output);
    }

    public function testStatusInstanceThrowsException(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('getInstance')->willThrowException(new \RuntimeException('error'));
        $command = new FlowyInstanceStatusCommand($engine, [DummyWorkflowInstanceId::class, 'fromString']);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'instance_id' => 'dummy-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Workflow instance not found', $output);
    }
}
