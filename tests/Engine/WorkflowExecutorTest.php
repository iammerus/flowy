<?php

declare(strict_types=1);

namespace Flowy\Tests\Engine;

use Flowy\Engine\WorkflowExecutor;
use Flowy\Persistence\PersistenceInterface;
use Flowy\Action\ActionResolver;
use Flowy\Condition\ConditionResolver;
use Flowy\Registry\DefinitionRegistryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;
use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\TransitionDefinition;

class WorkflowExecutorTest extends TestCase
{
    public function testProceedsAndCompletesWorkflow(): void
    {
        $instanceId = $this->createMock(WorkflowInstanceIdInterface::class);
        $instanceId->method('toString')->willReturn('id-1');
        $instance = new WorkflowInstance(
            $instanceId,
            'wf-id',
            '1.0',
            WorkflowStatus::PENDING,
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
        $step = new StepDefinition(
            'step1',
            [new ActionDefinition(fn(WorkflowContext $ctx) => $ctx->set('ran', true))],
            [new TransitionDefinition('step2')],
            'Step 1',
            null,
            true,
            'action',
            null
        );
        $step2 = new StepDefinition(
            'step2',
            [],
            [],
            'Step 2',
            null,
            false,
            'action',
            null
        );
        $definition = new WorkflowDefinition(
            'wf-id',
            '1.0',
            'step1',
            [$step, $step2]
        );
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->atLeastOnce())->method('save');
        $actionResolver = new ActionResolver();
        $conditionResolver = new class extends ConditionResolver {
            public function resolve($transition) { return null; }
        };
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $definitionRegistry->method('getDefinition')->willReturn($definition);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->atLeastOnce())->method('dispatch');
        $logger = $this->createMock(LoggerInterface::class);
        $executor = new WorkflowExecutor(
            $persistence,
            $actionResolver,
            $conditionResolver,
            $definitionRegistry,
            $eventDispatcher,
            $logger
        );
        $executor->proceed($instanceId);
        $this->assertEquals('step2', $instance->currentStepId);
        $this->assertEquals(WorkflowStatus::COMPLETED, $instance->status);
        $this->assertTrue($instance->context->get('ran'));
    }

    public function testActionFailureSetsStatusFailedAndDispatchesEvent(): void
    {
        $instanceId = $this->createMock(WorkflowInstanceIdInterface::class);
        $instanceId->method('toString')->willReturn('id-2');
        $instance = new WorkflowInstance(
            $instanceId,
            'wf-id',
            '1.0',
            WorkflowStatus::PENDING,
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
        $step = new StepDefinition(
            'step1',
            [new ActionDefinition(fn() => throw new \RuntimeException('fail'))],
            [],
            'Step 1',
            null,
            true,
            'action',
            null
        );
        $definition = new WorkflowDefinition(
            'wf-id',
            '1.0',
            'step1',
            [$step]
        );
        $persistence = $this->createMock(PersistenceInterface::class);
        $persistence->method('find')->willReturn($instance);
        $persistence->expects($this->atLeastOnce())->method('save');
        $actionResolver = new ActionResolver();
        $conditionResolver = new class extends ConditionResolver {
            public function resolve($transition) { return null; }
        };
        $definitionRegistry = $this->createMock(DefinitionRegistryInterface::class);
        $definitionRegistry->method('getDefinition')->willReturn($definition);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->atLeastOnce())->method('dispatch');
        $logger = $this->createMock(LoggerInterface::class);
        $executor = new WorkflowExecutor(
            $persistence,
            $actionResolver,
            $conditionResolver,
            $definitionRegistry,
            $eventDispatcher,
            $logger
        );
        $this->expectException(\RuntimeException::class);
        $executor->proceed($instanceId);
        $this->assertEquals(WorkflowStatus::FAILED, $instance->status);
        $this->assertStringContainsString('fail', $instance->errorDetails);
    }
}
