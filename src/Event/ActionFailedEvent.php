<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\WorkflowInstance;
use Throwable;

/**
 * Event dispatched when an action fails during execution.
 */
class ActionFailedEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly StepDefinition $step,
        public readonly ActionDefinition $action,
        public readonly Throwable $exception
    ) {
    }
}
