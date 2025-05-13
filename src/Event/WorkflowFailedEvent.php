<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\WorkflowInstance;
use Throwable;

/**
 * Event dispatched when a workflow instance fails.
 */
class WorkflowFailedEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly Throwable $reason
    ) {
    }
}
