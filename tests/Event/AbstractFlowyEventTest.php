<?php

declare(strict_types=1);

namespace Flowy\Tests\Event;

use Flowy\Event\AbstractFlowyEvent;
use PHPUnit\Framework\TestCase;

class DummyEvent extends AbstractFlowyEvent {}

/**
 * @covers \Flowy\Event\AbstractFlowyEvent
 */
class AbstractFlowyEventTest extends TestCase
{
    public function testPropagationControl(): void
    {
        $event = new DummyEvent();
        $this->assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }
}
