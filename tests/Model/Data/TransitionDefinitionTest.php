<?php

declare(strict_types=1);

namespace Flowy\Tests\Model\Data;

use Flowy\Model\Data\TransitionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Model\Data\TransitionDefinition
 */
class TransitionDefinitionTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $def = new TransitionDefinition('target-step');
        $this->assertSame('target-step', $def->targetStepId);
        $this->assertNull($def->conditionIdentifier);
        $this->assertNull($def->conditionType);
        $this->assertSame([], $def->conditionParameters);
        $this->assertNull($def->event);
    }

    public function testConditionTypeInferenceForClass(): void
    {
        $def = new TransitionDefinition('target', self::class);
        $this->assertSame(self::class, $def->conditionIdentifier);
        $this->assertSame('class', $def->conditionType);
    }

    public function testConditionTypeInferenceForService(): void
    {
        $def = new TransitionDefinition('target', 'service_id');
        $this->assertSame('service_id', $def->conditionIdentifier);
        $this->assertSame('service', $def->conditionType);
    }

    public function testExplicitConditionType(): void
    {
        $def = new TransitionDefinition('target', 'foo', 'custom');
        $this->assertSame('custom', $def->conditionType);
    }

    public function testWithParametersAndEvent(): void
    {
        $def = new TransitionDefinition('target', null, null, ['foo' => 'bar'], 'eventName');
        $this->assertSame(['foo' => 'bar'], $def->conditionParameters);
        $this->assertSame('eventName', $def->event);
    }
}
