<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched when a workflow instance successfully completes.
 */
class WorkflowCompletedEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance
    ) {
    }
}
