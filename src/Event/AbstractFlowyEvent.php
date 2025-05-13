<?php

declare(strict_types=1);

namespace Flowy\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base class for all Flowy events.
 * Implements StoppableEventInterface to allow event propagation to be stopped.
 */
abstract class AbstractFlowyEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
