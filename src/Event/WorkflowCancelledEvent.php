<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\WorkflowInstance;

/**
 * Event dispatched when a workflow instance is cancelled.
 */
class WorkflowCancelledEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly ?string $reason = null
    ) {
    }
}
