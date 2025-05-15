<?php

declare(strict_types=1);

namespace Flowy\Tests\Engine;

use Flowy\Engine\WorkflowEngineService;
use Flowy\Engine\WorkflowExecutor;
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

class WorkflowEngineServiceTest extends TestCase
{
    public function testStartCreatesAndExecutesInstance(): void
    {
        $definition = new WorkflowDefinition(
            'wf-id',
            '1.0',
            'step1',
            [new StepDefinition('step1', [], [], null, null, true, 'action', null)]
        );
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $definitionRegistry->method('getDefinition')->willReturn($definition);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutor::class);
        $executor->expects($this->once())->method('proceed');
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry);
        $instance = $engine->start('wf-id');
        $this->assertInstanceOf(WorkflowInstance::class, $instance);
        $this->assertSame('wf-id', $instance->definitionId);
        $this->assertSame(WorkflowStatus::PENDING, $instance->status);
    }

    public function testPausePausesRunningInstance(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->once())->method('save');
        $executor = $this->createMock(WorkflowExecutor::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry);
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
        $executor = $this->createMock(WorkflowExecutor::class);
        $executor->expects($this->once())->method('proceed')->with($id);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry);
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
        $executor = $this->createMock(WorkflowExecutor::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry);
        $engine->cancel($id);
        $this->assertSame(WorkflowStatus::CANCELLED, $instance->status);
    }

    public function testGetInstanceReturnsInstance(): void
    {
        $id = $this->createMock(WorkflowInstanceIdInterface::class);
        $instance = $this->makeInstance($id, WorkflowStatus::RUNNING);
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $executor = $this->createMock(WorkflowExecutor::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry);
        $this->assertSame($instance, $engine->getInstance($id));
    }

    public function testFindInstancesByStatusDelegatesToPersistence(): void
    {
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->expects($this->once())
            ->method('findInstancesByStatus')
            ->with(WorkflowStatus::RUNNING, 'wf-id', 5)
            ->willReturn([]);
        $executor = $this->createMock(WorkflowExecutor::class);
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $engine = new WorkflowEngineService($executor, $persistence, $definitionRegistry);
        $result = $engine->findInstancesByStatus(WorkflowStatus::RUNNING, 'wf-id', 5);
        $this->assertIsArray($result);
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
