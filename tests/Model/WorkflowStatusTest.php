<?php

declare(strict_types=1);

namespace Flowy\Tests\Model;

use Flowy\Model\WorkflowStatus;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Model\WorkflowStatus
 */
class WorkflowStatusTest extends TestCase
{
    public function testIsTerminal(): void
    {
        $this->assertFalse(WorkflowStatus::PENDING->isTerminal());
        $this->assertFalse(WorkflowStatus::RUNNING->isTerminal());
        $this->assertFalse(WorkflowStatus::PAUSED->isTerminal());
        $this->assertTrue(WorkflowStatus::COMPLETED->isTerminal());
        $this->assertTrue(WorkflowStatus::FAILED->isTerminal());
        $this->assertTrue(WorkflowStatus::CANCELLED->isTerminal());
    }

    public function testCanTransitionTo(): void
    {
        // PENDING
        $this->assertTrue(WorkflowStatus::PENDING->canTransitionTo(WorkflowStatus::RUNNING));
        $this->assertTrue(WorkflowStatus::PENDING->canTransitionTo(WorkflowStatus::CANCELLED));
        $this->assertTrue(WorkflowStatus::PENDING->canTransitionTo(WorkflowStatus::FAILED));
        $this->assertFalse(WorkflowStatus::PENDING->canTransitionTo(WorkflowStatus::COMPLETED));
        // RUNNING
        $this->assertTrue(WorkflowStatus::RUNNING->canTransitionTo(WorkflowStatus::PAUSED));
        $this->assertTrue(WorkflowStatus::RUNNING->canTransitionTo(WorkflowStatus::COMPLETED));
        $this->assertTrue(WorkflowStatus::RUNNING->canTransitionTo(WorkflowStatus::FAILED));
        $this->assertTrue(WorkflowStatus::RUNNING->canTransitionTo(WorkflowStatus::CANCELLED));
        $this->assertFalse(WorkflowStatus::RUNNING->canTransitionTo(WorkflowStatus::PENDING));
        // PAUSED
        $this->assertTrue(WorkflowStatus::PAUSED->canTransitionTo(WorkflowStatus::RUNNING));
        $this->assertTrue(WorkflowStatus::PAUSED->canTransitionTo(WorkflowStatus::CANCELLED));
        $this->assertTrue(WorkflowStatus::PAUSED->canTransitionTo(WorkflowStatus::FAILED));
        $this->assertFalse(WorkflowStatus::PAUSED->canTransitionTo(WorkflowStatus::COMPLETED));
        // COMPLETED
        $this->assertFalse(WorkflowStatus::COMPLETED->canTransitionTo(WorkflowStatus::PENDING));
        $this->assertFalse(WorkflowStatus::COMPLETED->canTransitionTo(WorkflowStatus::FAILED));
        // FAILED
        $this->assertTrue(WorkflowStatus::FAILED->canTransitionTo(WorkflowStatus::PENDING));
        $this->assertFalse(WorkflowStatus::FAILED->canTransitionTo(WorkflowStatus::RUNNING));
        // CANCELLED
        $this->assertFalse(WorkflowStatus::CANCELLED->canTransitionTo(WorkflowStatus::PENDING));
    }

    public function testEnumCases(): void
    {
        $expected = [
            'PENDING', 'RUNNING', 'PAUSED', 'COMPLETED', 'FAILED', 'CANCELLED'
        ];
        $actual = array_map(fn($case) => $case->name, WorkflowStatus::cases());
        $this->assertSame($expected, $actual);
    }
}
