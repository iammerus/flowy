<?php

declare(strict_types=1);

namespace Flowy\Tests\Action;

use Flowy\Action\ActionInterface;
use Flowy\Context\WorkflowContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Action\ActionInterface
 */
class ActionInterfaceTest extends TestCase
{
    public function testActionInterfaceCanBeImplementedAndExecuted(): void
    {
        $context = new WorkflowContext(['foo' => 'bar']);
        $action = new class implements ActionInterface {
            public bool $executed = false;
            public function execute(WorkflowContext $context): void
            {
                $context->set('executed', true);
                $this->executed = true;
            }
        };
        $this->assertInstanceOf(ActionInterface::class, $action);
        $action->execute($context);
        $this->assertTrue($action->executed);
        $this->assertTrue($context->get('executed'));
    }
}
