<?php

declare(strict_types=1);

namespace Flowy\Tests\Condition;

use Flowy\Condition\ConditionInterface;
use Flowy\Context\WorkflowContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Condition\ConditionInterface
 */
class ConditionInterfaceTest extends TestCase
{
    public function testConditionInterfaceCanBeImplementedAndEvaluated(): void
    {
        $context = new WorkflowContext(['value' => 42]);
        $condition = new class implements ConditionInterface {
            public function evaluate(WorkflowContext $context): bool
            {
                return $context->get('value') === 42;
            }
        };
        $this->assertInstanceOf(ConditionInterface::class, $condition);
        $this->assertTrue($condition->evaluate($context));
        $context->set('value', 0);
        $this->assertFalse($condition->evaluate($context));
    }
}
