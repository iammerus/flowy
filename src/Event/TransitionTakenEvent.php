<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\TransitionDefinition;
use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched when a transition is taken from one step to another.
 */
class TransitionTakenEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly StepDefinition $fromStep,
        public readonly StepDefinition $toStep,
        public readonly TransitionDefinition $transition
    ) {
    }
}
