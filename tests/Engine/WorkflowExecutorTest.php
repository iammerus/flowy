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

    public function testLogsAllLifecycleEventsAndErrors(): void
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
        $logger->expects($this->atLeastOnce())->method('info');
        $logger->expects($this->never())->method('error');
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

    public function testLogsErrorOnActionFailure(): void
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
        $logger->expects($this->atLeastOnce())->method('error')->with(
            $this->stringContains('Action execution failed'),
            $this->arrayHasKey('exception')
        );
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

    public function testActionFailureWithRetryPolicySchedulesRetry(): void
    {
        $instanceId = $this->createMock(WorkflowInstanceIdInterface::class);
        $instanceId->method('toString')->willReturn('id-retry');
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
        $retryPolicy = new \Flowy\Model\Data\RetryPolicy(2, 5);
        $step = new StepDefinition(
            'step1',
            [new ActionDefinition(fn() => throw new \RuntimeException('fail'))],
            [],
            'Step 1',
            null,
            true,
            'action',
            $retryPolicy
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
        $logger->expects($this->once())->method('warning')->with(
            $this->stringContains('Action failed, retry scheduled'),
            $this->arrayHasKey('exception')
        );
        $executor = new WorkflowExecutor(
            $persistence,
            $actionResolver,
            $conditionResolver,
            $definitionRegistry,
            $eventDispatcher,
            $logger
        );
        $executor->proceed($instanceId);
        $this->assertEquals(WorkflowStatus::PENDING, $instance->status);
        $this->assertEquals(1, $instance->retryAttempts);
        $this->assertNotNull($instance->scheduledAt);
        $this->assertStringContainsString('fail', $instance->errorDetails);
    }

    public function testActionFailureExceedsRetryPolicyFailsInstance(): void
    {
        $instanceId = $this->createMock(WorkflowInstanceIdInterface::class);
        $instanceId->method('toString')->willReturn('id-retry-fail');
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
            2, // Already retried twice
            1
        );
        $retryPolicy = new \Flowy\Model\Data\RetryPolicy(2, 5);
        $step = new StepDefinition(
            'step1',
            [new ActionDefinition(fn() => throw new \RuntimeException('fail again'))],
            [],
            'Step 1',
            null,
            true,
            'action',
            $retryPolicy
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
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains('Action execution failed'),
            $this->arrayHasKey('exception')
        );
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
        $this->assertStringContainsString('fail again', $instance->errorDetails);
    }

    public function testStepTimeoutFailsInstanceAndDispatchesEvent(): void
    {
        $instanceId = $this->createMock(WorkflowInstanceIdInterface::class);
        $instanceId->method('toString')->willReturn('id-timeout');
        $now = new \DateTimeImmutable();
        $instance = new WorkflowInstance(
            $instanceId,
            'wf-id',
            '1.0',
            WorkflowStatus::PENDING,
            new WorkflowContext([]),
            $now->sub(new \DateInterval('PT1H')),
            $now,
            null,
            'step1',
            [],
            null,
            0,
            1,
            null,
            null,
            null,
            $now->sub(new \DateInterval('PT10M')) // stepStartedAt 10 minutes ago
        );
        $step = new StepDefinition(
            'step1',
            [new ActionDefinition(fn() => true)],
            [],
            'Step 1',
            null,
            true,
            'action',
            null,
            'PT5M' // 5 minute timeout
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
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            $this->isInstanceOf(\Flowy\Event\WorkflowFailedEvent::class)
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains('Step timed out'),
            $this->arrayHasKey('instance_id')
        );
        $executor = new WorkflowExecutor(
            $persistence,
            $actionResolver,
            $conditionResolver,
            $definitionRegistry,
            $eventDispatcher,
            $logger
        );
        $executor->proceed($instanceId);
        $this->assertEquals(WorkflowStatus::FAILED, $instance->status);
        $this->assertStringContainsString('timed out', $instance->errorDetails);
    }

    public function testEventBasedTransitionIsTakenWhenSignalPresent(): void
    {
        $instanceId = $this->createMock(WorkflowInstanceIdInterface::class);
        $instanceId->method('toString')->willReturn('id-event');
        $now = new \DateTimeImmutable();
        $instance = new WorkflowInstance(
            $instanceId,
            'wf-id',
            '1.0',
            WorkflowStatus::PENDING,
            new WorkflowContext([]),
            $now->sub(new \DateInterval('PT1H')),
            $now,
            null,
            'start',
            [],
            null,
            0,
            1,
            null,
            null,
            null,
            $now,
            [
                ['name' => 'my_event', 'payload' => ['foo' => 'bar'], 'timestamp' => $now]
            ]
        );
        $eventTransition = new \Flowy\Model\Data\TransitionDefinition(
            'end',
            null,
            null,
            [],
            'my_event'
        );
        $step = new StepDefinition(
            'start',
            [],
            [$eventTransition],
            'Start',
            null,
            true,
            'action',
            null
        );
        $endStep = new StepDefinition(
            'end',
            [],
            [],
            'End',
            null,
            false,
            'action',
            null
        );
        $definition = new WorkflowDefinition(
            'wf-id',
            '1.0',
            'start',
            [$step, $endStep]
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
        $this->assertEquals('end', $instance->currentStepId);
        $this->assertEmpty($instance->signals, 'Signal should be removed after use');
    }

    public function testEventBasedTransitionIsNotTakenWithoutSignal(): void
    {
        $instanceId = $this->createMock(WorkflowInstanceIdInterface::class);
        $instanceId->method('toString')->willReturn('id-event-miss');
        $now = new \DateTimeImmutable();
        $instance = new WorkflowInstance(
            $instanceId,
            'wf-id',
            '1.0',
            WorkflowStatus::PENDING,
            new WorkflowContext([]),
            $now->sub(new \DateInterval('PT1H')),
            $now,
            null,
            'start',
            [],
            null,
            0,
            1,
            null,
            null,
            null,
            $now,
            [] // No signals
        );
        $eventTransition = new \Flowy\Model\Data\TransitionDefinition(
            'end',
            null,
            null,
            [],
            'my_event'
        );
        $step = new StepDefinition(
            'start',
            [],
            [$eventTransition],
            'Start',
            null,
            true,
            'action',
            null
        );
        $endStep = new StepDefinition(
            'end',
            [],
            [],
            'End',
            null,
            false,
            'action',
            null
        );
        $definition = new WorkflowDefinition(
            'wf-id',
            '1.0',
            'start',
            [$step, $endStep]
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
        $this->assertEquals('start', $instance->currentStepId, 'Should remain on start step');
        $this->assertEmpty($instance->signals, 'No signals should be present');
    }
}
