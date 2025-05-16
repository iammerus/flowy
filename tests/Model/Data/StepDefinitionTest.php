<?php

declare(strict_types=1);

namespace Flowy\Tests\Model\Data;

use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\TransitionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Model\Data\StepDefinition
 */
class StepDefinitionTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $action = new ActionDefinition('Some\\Action');
        $transition = new TransitionDefinition('next-step');
        $step = new StepDefinition(
            'step1',
            [$action],
            [$transition],
            'Step 1',
            'Description',
            true,
            'action',
            null,
            'PT5M'
        );
        $this->assertSame('step1', $step->id);
        $this->assertSame('Step 1', $step->name);
        $this->assertSame('Description', $step->description);
        $this->assertTrue($step->isInitial);
        $this->assertSame('action', $step->type);
        $this->assertNull($step->retryPolicy);
        $this->assertSame('PT5M', $step->timeoutDuration);
        $this->assertSame([$action], $step->actions);
        $this->assertSame([$transition], $step->transitions);
    }

    public function testThrowsIfActionIsNotActionDefinition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StepDefinition('step', [new \stdClass()], []);
    }

    public function testThrowsIfTransitionIsNotTransitionDefinition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $action = new ActionDefinition('Some\\Action');
        new StepDefinition('step', [$action], [new \stdClass()]);
    }
}
