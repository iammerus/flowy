<?php

declare(strict_types=1);

namespace Flowy\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Null implementation of EventDispatcherInterface for no-op event dispatching.
 * Used as a default to maintain backward compatibility in tests and non-event-driven contexts.
 */
class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
