<?php

declare(strict_types=1);

namespace Flowy\Tests\Event;

use Flowy\Event\WorkflowStartedEvent;
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
 * @covers \Flowy\Event\WorkflowStartedEvent
 */
class WorkflowStartedEventTest extends TestCase
{
    public function testEventStoresWorkflowInstance(): void
    {
        $instance = new WorkflowInstance(
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
        $event = new WorkflowStartedEvent($instance);
        $this->assertSame($instance, $event->instance);
        $this->assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }
}
