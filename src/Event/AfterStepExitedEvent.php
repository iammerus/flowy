<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\Data\StepDefinition;
use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched after a workflow step has been exited.
 */
class AfterStepExitedEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly StepDefinition $step
    ) {
    }
}
