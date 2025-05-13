<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched when a workflow instance is started.
 */
class WorkflowStartedEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance
    ) {
    }
}
