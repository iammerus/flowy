<?php

declare(strict_types=1);

namespace Flowy\Event;

use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use DateTimeImmutable;

/**
 * Event dispatched when a signal is received for a workflow instance.
 *
 * @psalm-immutable
 */
final class WorkflowSignalReceivedEvent extends AbstractFlowyEvent
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly string $signalName,
        public readonly array $payload,
        public readonly DateTimeImmutable $timestamp
    ) {
        parent::__construct($instance);
    }
}
