<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched before an action is executed within a step.
 */
class BeforeActionExecutedEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly StepDefinition $step,
        public readonly ActionDefinition $action
    ) {
    }
}
