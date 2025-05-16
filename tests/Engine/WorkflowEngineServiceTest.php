<?php

declare(strict_types=1);

namespace Flowy\Tests\Engine;

use Flowy\Engine\WorkflowEngineService;
use Flowy\Engine\WorkflowExecutorInterface;
use Flowy\Persistence\PersistenceInterface;
use Flowy\Registry\DefinitionRegistryInterface;
use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;
use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\TransitionDefinition;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

class DummyWorkflowInstanceId implements \Flowy\Model\WorkflowInstanceIdInterface
{
    public static function generate(): static
    {
        return new static();
    }
    public static function fromString(string $identifier): static
    {
        return new static();
    }
    public function toString(): string
    {
        return 'dummy-id';
    }
    public function equals(\Flowy\Model\WorkflowInstanceIdInterface $other): bool
    {
        return $other instanceof self && $other->toString() === $this->toString();
    }
    public function __toString(): string
    {
        return $this->toString();
    }
}

class WorkflowEngineServiceTest extends TestCase
{
    public function testStartCreatesAndExecutesInstance(): void
    {
        $definition = new WorkflowDefinition(
            DummyWorkflowInstanceId::class,
            '1.0',
            'step1',
            [new StepDefinition('step1', [], [], null, null, true, 'action', null)]
        );
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $definitionRegistry->method('getDefinition')->willReturn($definition);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $executor->expects($this->once())->method('proceed');
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $this->createMock(\Psr\Log\LoggerInterface::class));
        $instance = $engine->start('wf-id');
        $this->assertInstanceOf(WorkflowInstance::class, $instance);
        $this->assertSame(DummyWorkflowInstanceId::class, $instance->definitionId);
        $this->assertSame(WorkflowStatus::PENDING, $instance->status);
    }

    public function testPausePausesRunningInstance(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $this->createMock(\Psr\Log\LoggerInterface::class));
        $engine->pause($id);
        $this->assertSame(WorkflowStatus::PAUSED, $instance->status);
    }

    public function testResumeResumesPausedInstanceAndExecutes(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::PAUSED);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $executor->expects($this->once())->method('proceed')->with($id);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $this->createMock(\Psr\Log\LoggerInterface::class));
        $engine->resume($id);
        $this->assertSame(WorkflowStatus::RUNNING, $instance->status);
    }

    public function testCancelCancelsNonTerminalInstance(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $this->createMock(\Psr\Log\LoggerInterface::class));
        $engine->cancel($id);
        $this->assertSame(WorkflowStatus::CANCELLED, $instance->status);
    }

    public function testGetInstanceReturnsInstance(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $this->createMock(\Psr\Log\LoggerInterface::class));
        $this->assertSame($instance, $engine->getInstance($id));
    }

    public function testFindInstancesByStatusDelegatesToPersistence(): void
    {
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->expects($this->once())
            ->method('findInstancesByStatus')
            ->with(WorkflowStatus::RUNNING, 'wf-id', 5)
            ->willReturn([]);
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $this->createMock(\Psr\Log\LoggerInterface::class));
        $result = $engine->findInstancesByStatus(WorkflowStatus::RUNNING, 'wf-id', 5);
        $this->assertIsArray($result);
    }

    public function testStartLogsWorkflowInstanceStarted(): void
    {
        $definition = new WorkflowDefinition(
            DummyWorkflowInstanceId::class,
            '1.0',
            'step1',
            [new StepDefinition('step1', [], [], null, null, true, 'action', null)]
        );
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $definitionRegistry->method('getDefinition')->willReturn($definition);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $executor->expects($this->once())->method('proceed');
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Workflow instance started'),
                $this->arrayHasKey('workflow_id')
            );
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger);
        $engine->start('wf-id');
    }

    public function testPauseLogsPaused(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('paused'), $this->arrayHasKey('instance_id'));
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger);
        $engine->pause($id);
    }

    public function testResumeLogsResumed(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::PAUSED);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $executor->expects($this->once())->method('proceed')->with($id);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('resumed'), $this->arrayHasKey('instance_id'));
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger);
        $engine->resume($id);
    }

    public function testCancelLogsCancelled(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('cancelled'), $this->arrayHasKey('instance_id'));
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger);
        $engine->cancel($id);
    }

    public function testRetryFailedStepResetsAndProceeds(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::FAILED);
        $instance->retryAttempts = 3;
        $instance->errorDetails = 'Some error';
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $executor->expects($this->once())->method('proceed')->with($id);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('retry initiated'), $this->arrayHasKey('instance_id'));
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger);
        $engine->retryFailedStep($id);
        $this->assertSame(0, $instance->retryAttempts);
        $this->assertSame(WorkflowStatus::PENDING, $instance->status);
        $this->assertNull($instance->errorDetails);
    }

    public function testResumeDispatchesWorkflowStartedEvent(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::PAUSED);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $executor->expects($this->once())->method('proceed')->with($id);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(\Flowy\Event\WorkflowStartedEvent::class));
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger, $eventDispatcher);
        $engine->resume($id);
    }

    public function testCancelDispatchesWorkflowCancelledEvent(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(\Flowy\Event\WorkflowCancelledEvent::class));
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger, $eventDispatcher);
        $engine->cancel($id);
    }

    public function testRetryFailedStepDispatchesWorkflowStartedEvent(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::FAILED);
        $instance->retryAttempts = 3;
        $instance->errorDetails = 'Some error';
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $executor->expects($this->once())->method('proceed')->with($id);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(\Flowy\Event\WorkflowStartedEvent::class));
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger, $eventDispatcher);
        $engine->retryFailedStep($id);
        $this->assertSame(0, $instance->retryAttempts);
        $this->assertSame(WorkflowStatus::PENDING, $instance->status);
        $this->assertNull($instance->errorDetails);
    }

    public function testSignalAddsSignalAndPersistsInstance(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save')->with($instance);
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Signal received'),
                $this->arrayHasKey('signal')
            );
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger);
        $engine->signal($id, 'my_signal', ['foo' => 'bar']);
        $this->assertNotEmpty($instance->signals);
        $lastSignal = end($instance->signals);
        $this->assertSame('my_signal', $lastSignal['name']);
        $this->assertSame(['foo' => 'bar'], $lastSignal['payload']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $lastSignal['timestamp']);
    }

    public function testSignalThrowsIfInstanceNotFound(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn(null);
        $executor = $this->createMock(WorkflowExecutorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry, $logger);
        $this->expectException(\Flowy\Exception\DefinitionNotFoundException::class);
        $engine->signal($id, 'signal', []);
    }

    private function makeInstance($id, WorkflowStatus $status): WorkflowInstance
    {
        return new WorkflowInstance(
            $id,
            'wf-id',
            '1.0',
            $status,
            new WorkflowContext([]),
            new \DateTimeImmutable('-1 hour'),
            new \DateTimeImmutable(),
            null,
            null,
            [],
            null,
            0,
            1
        );
    }
}
