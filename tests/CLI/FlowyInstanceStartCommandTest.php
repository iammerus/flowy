<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use Flowy\CLI\FlowyInstanceStartCommand;
use Flowy\Engine\WorkflowEngineInterface;
use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Flowy\Tests\CLI\DummyWorkflowInstanceId;

class FlowyInstanceStartCommandTest extends TestCase
{
    public function testStartInstanceSuccess(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $instance = new WorkflowInstance(
            new DummyWorkflowInstanceId(),
            'wf-id',
            '1.0',
            WorkflowStatus::PENDING,
            new WorkflowContext(['foo' => 'bar']),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'biz-key',
            'step1',
            [],
            null,
            0,
            1
        );
        $engine->expects($this->once())
            ->method('start')
            ->with('wf-id', '1.0', $this->isInstanceOf(WorkflowContext::class), 'biz-key')
            ->willReturn($instance);
        $command = new FlowyInstanceStartCommand($engine);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'workflow_id' => 'wf-id',
            'version' => '1.0',
            '--business-key' => 'biz-key',
            '--context' => '{"foo":"bar"}'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Workflow instance started successfully', $output);
        $this->assertStringContainsString('Instance ID: dummy-id', $output);
        $this->assertStringContainsString('Workflow ID: wf-id', $output);
        $this->assertStringContainsString('Status: PENDING', $output);
        $this->assertStringContainsString('Business Key: biz-key', $output);
    }

    public function testInvalidContextJson(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $command = new FlowyInstanceStartCommand($engine);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'workflow_id' => 'wf-id',
            '--context' => '{invalid_json}'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid context JSON', $output);
    }

    public function testWorkflowDefinitionNotFound(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('start')->willThrowException(new \Flowy\Exception\DefinitionNotFoundException('not found'));
        $command = new FlowyInstanceStartCommand($engine);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'workflow_id' => 'wf-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Workflow definition not found', $output);
    }

    public function testEngineThrowsOtherException(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('start')->willThrowException(new \RuntimeException('engine error'));
        $command = new FlowyInstanceStartCommand($engine);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'workflow_id' => 'wf-id'
        ]);
        $output = $tester->getDisplay();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to start workflow instance', $output);
    }
}
