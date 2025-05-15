<?php

declare(strict_types=1);

namespace Flowy\Tests\Model\ValueObject;

use Flowy\Model\ValueObject\WorkflowInstanceId;
use Flowy\Model\WorkflowInstanceIdInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Model\ValueObject\WorkflowInstanceId
 */
class WorkflowInstanceIdTest extends TestCase
{
    public function testGenerateReturnsValidUuidInstance(): void
    {
        $id = WorkflowInstanceId::generate();
        $this->assertInstanceOf(WorkflowInstanceIdInterface::class, $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-fA-F-]{36}$/',
            $id->toString()
        );
    }

    public function testFromStringReturnsSameUuid(): void
    {
        $id1 = WorkflowInstanceId::generate();
        $id2 = WorkflowInstanceId::fromString($id1->toString());
        $this->assertTrue($id1->equals($id2));
        $this->assertSame($id1->toString(), $id2->toString());
    }

    public function testToStringAndMagicToStringAreEquivalent(): void
    {
        $id = WorkflowInstanceId::generate();
        $this->assertSame($id->toString(), (string)$id);
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $id1 = WorkflowInstanceId::generate();
        $id2 = WorkflowInstanceId::generate();
        $this->assertFalse($id1->equals($id2));
    }

    public function testFromStringThrowsForInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WorkflowInstanceId::fromString('not-a-uuid');
    }
}
