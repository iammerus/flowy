<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\Data\StepDefinition;
use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched before a workflow step is entered.
 */
class BeforeStepEnteredEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly StepDefinition $step
    ) {
    }
}
