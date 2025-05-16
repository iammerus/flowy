<?php

declare(strict_types=1);

namespace Flowy\Tests\Model\Data;

use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\TransitionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Model\Data\WorkflowDefinition
 */
class WorkflowDefinitionTest extends TestCase
{
    private function makeStep(string $id, bool $isInitial = false): StepDefinition
    {
        $action = new ActionDefinition('Some\\Action');
        $transition = new TransitionDefinition('next-step');
        return new StepDefinition(
            $id,
            [$action],
            [$transition],
            null,
            null,
            $isInitial
        );
    }

    public function testValidConstructionAndAccessors(): void
    {
        $step1 = $this->makeStep('step1', true);
        $step2 = $this->makeStep('step2');
        $def = new WorkflowDefinition(
            'wf-id',
            '1.0',
            'step1',
            [$step1, $step2],
            'Test Workflow',
            'A test workflow',
            ['foo' => 'bar']
        );
        $this->assertSame('wf-id', $def->id);
        $this->assertSame('1.0', $def->version);
        $this->assertSame('Test Workflow', $def->name);
        $this->assertSame('A test workflow', $def->description);
        $this->assertSame('step1', $def->initialStepId);
        $this->assertSame(['foo' => 'bar'], $def->initialContextSchema);
        $this->assertCount(2, $def->steps);
        $this->assertSame($step1, $def->getStep('step1'));
        $this->assertSame($step1, $def->getInitialStep());
        $this->assertNull($def->getStep('not-exist'));
    }

    public function testThrowsIfNoSteps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkflowDefinition('wf', '1.0', 'step1', []);
    }

    public function testThrowsIfDuplicateStepIds(): void
    {
        $step = $this->makeStep('dup', true);
        $this->expectException(\InvalidArgumentException::class);
        new WorkflowDefinition('wf', '1.0', 'dup', [$step, $step]);
    }

    public function testThrowsIfInitialStepIdNotFound(): void
    {
        $step = $this->makeStep('step1');
        $this->expectException(\InvalidArgumentException::class);
        new WorkflowDefinition('wf', '1.0', 'notfound', [$step]);
    }

    public function testThrowsIfStepIsNotStepDefinition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkflowDefinition('wf', '1.0', 'step1', [new \stdClass()]);
    }
}
