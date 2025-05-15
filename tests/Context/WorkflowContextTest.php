<?php

declare(strict_types=1);

namespace Flowy\Tests\Context;

use Flowy\Context\WorkflowContext;
use Flowy\Exception\InvalidContextKeyException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Context\WorkflowContext
 */
class WorkflowContextTest extends TestCase
{
    public function testConstructAndGetSetHasRemove(): void
    {
        $context = new WorkflowContext(['foo' => 'bar']);
        $this->assertTrue($context->has('foo'));
        $this->assertSame('bar', $context->get('foo'));
        $context->set('baz', 42);
        $this->assertTrue($context->has('baz'));
        $this->assertSame(42, $context->get('baz'));
        $context->remove('foo');
        $this->assertFalse($context->has('foo'));
        $this->assertNull($context->get('foo'));
    }

    public function testGetReturnsDefaultIfKeyMissing(): void
    {
        $context = new WorkflowContext();
        $this->assertSame('default', $context->get('missing', 'default'));
    }

    public function testAllAndToArrayReturnSameData(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $context = new WorkflowContext($data);
        $this->assertSame($data, $context->all());
        $this->assertSame($data, $context->toArray());
    }

    public function testMergeWithArrayAndContext(): void
    {
        $context = new WorkflowContext(['a' => 1]);
        $context->merge(['b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $context->all());
        $other = new WorkflowContext(['c' => 3]);
        $context->merge($other);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $context->all());
    }

    public function testInvalidKeyThrowsException(): void
    {
        $context = new WorkflowContext();
        $this->expectException(InvalidContextKeyException::class);
        $context->set('', 'value');
    }

    public function testArrayAccess(): void
    {
        $context = new WorkflowContext();
        $context['foo'] = 'bar';
        $this->assertTrue(isset($context['foo']));
        $this->assertSame('bar', $context['foo']);
        unset($context['foo']);
        $this->assertFalse(isset($context['foo']));
    }

    public function testIteratorAggregate(): void
    {
        $context = new WorkflowContext(['a' => 1, 'b' => 2]);
        $result = [];
        foreach ($context as $k => $v) {
            $result[$k] = $v;
        }
        $this->assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testCountable(): void
    {
        $context = new WorkflowContext(['a' => 1, 'b' => 2]);
        $this->assertCount(2, $context);
    }

    public function testJsonSerializeAndFromJson(): void
    {
        $context = new WorkflowContext(['foo' => 'bar', 'baz' => 42]);
        $json = json_encode($context, JSON_THROW_ON_ERROR);
        $restored = WorkflowContext::fromJson($json);
        $this->assertEquals($context->all(), $restored->all());
    }

    public function testRemoveNonExistentKeyDoesNotThrow(): void
    {
        $context = new WorkflowContext(['foo' => 'bar']);
        $context->remove('not-present');
        $this->assertTrue(true, 'No exception thrown');
    }
}
