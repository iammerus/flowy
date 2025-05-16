<?php

declare(strict_types=1);

namespace Flowy\Tests\Model\Data;

use Flowy\Model\Data\ActionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Flowy\Model\Data\ActionDefinition
 */
class ActionDefinitionTest extends TestCase
{
    public function testStringIdentifierConstruction(): void
    {
        $def = new ActionDefinition('Some\\Action', ['foo' => 'bar'], 'desc');
        $this->assertSame('Some\\Action', $def->actionIdentifier);
        $this->assertSame(['foo' => 'bar'], $def->parameters);
        $this->assertSame('desc', $def->description);
    }

    public function testCallableIdentifierConstruction(): void
    {
        $callable = fn() => null;
        $def = new ActionDefinition($callable);
        $this->assertSame($callable, $def->actionIdentifier);
        $this->assertSame([], $def->parameters);
        $this->assertNull($def->description);
    }
}
