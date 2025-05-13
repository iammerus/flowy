<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched after an action has been successfully executed.
 */
class AfterActionExecutedEvent extends AbstractFlowyEvent
{
    /**
     * @param WorkflowInstance $instance The workflow instance.
     * @param StepDefinition $step The step definition.
     * @param ActionDefinition $action The action definition.
     * @param mixed $result The result of the action (if any, for future use, currently void).
     */
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly StepDefinition $step,
        public readonly ActionDefinition $action,
        public readonly mixed $result = null // Currently actions return void, placeholder for future.
    ) {
    }
}
