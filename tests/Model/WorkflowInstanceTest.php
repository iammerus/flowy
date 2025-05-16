<?php

declare(strict_types=1);

namespace Flowy\Tests\Model;

use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class DummyWorkflowInstanceId implements WorkflowInstanceIdInterface
{
    public function toString(): string { return 'dummy-id'; }
    public function equals(WorkflowInstanceIdInterface $other): bool { return $other->toString() === 'dummy-id'; }
    public static function fromString(string $identifier): static { return new static(); }
    public static function generate(): static { return new static(); }
    public function __toString(): string { return $this->toString(); }
}

/**
 * @covers \Flowy\Model\WorkflowInstance
 */
class WorkflowInstanceTest extends TestCase
{
    private function makeInstance(): WorkflowInstance
    {
        return new WorkflowInstance(
            new DummyWorkflowInstanceId(),
            'def-id',
            'v1',
            WorkflowStatus::PENDING,
            new WorkflowContext(['foo' => 'bar']),
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable(),
            'biz-key',
            'step-1',
            [],
            null,
            0,
            1,
            null,
            null,
            null
        );
    }

    public function testConstructionAndProperties(): void
    {
        $instance = $this->makeInstance();
        $this->assertSame('dummy-id', $instance->id->toString());
        $this->assertSame('def-id', $instance->definitionId);
        $this->assertSame('v1', $instance->definitionVersion);
        $this->assertSame(WorkflowStatus::PENDING, $instance->status);
        $this->assertInstanceOf(WorkflowContext::class, $instance->context);
        $this->assertSame('biz-key', $instance->businessKey);
        $this->assertSame('step-1', $instance->currentStepId);
        $this->assertIsArray($instance->history);
        $this->assertNull($instance->errorDetails);
        $this->assertSame(0, $instance->retryAttempts);
        $this->assertSame(1, $instance->version);
        $this->assertNull($instance->lockedBy);
        $this->assertNull($instance->lockExpiresAt);
        $this->assertNull($instance->scheduledAt);
    }

    public function testAddHistoryEvent(): void
    {
        $instance = $this->makeInstance();
        $instance->addHistoryEvent('Test message', 'step-1');
        $this->assertNotEmpty($instance->history);
        $event = end($instance->history);
        $this->assertSame('Test message', $event['message']);
        $this->assertSame('step-1', $event['stepId']);
        $this->assertInstanceOf(DateTimeImmutable::class, $event['timestamp']);
    }
}
