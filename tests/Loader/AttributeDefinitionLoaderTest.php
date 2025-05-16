<?php

declare(strict_types=1);

namespace Flowy\Tests\Loader;

use Flowy\Loader\AttributeDefinitionLoader;
use Flowy\Model\Data\TransitionDefinition;
use PHPUnit\Framework\TestCase;

#[\Flowy\Attribute\Workflow(id: 'event_workflow', version: '1.0', name: 'Event Workflow')]
class EventWorkflow
{
    #[\Flowy\Attribute\Step(id: 'start', initial: true)]
    #[\Flowy\Attribute\Transition(to: 'end', event: 'my_event')]
    public function start() {}

    #[\Flowy\Attribute\Step(id: 'end')]
    public function end() {}
}

class AttributeDefinitionLoaderTest extends TestCase
{
    public function testLoadsEventBasedTransition(): void
    {
        $loader = new AttributeDefinitionLoader();
        $definition = $loader->loadFromClass(EventWorkflow::class);
        $startStep = $definition->steps['start'] ?? null;
        $this->assertNotNull($startStep, 'Start step should be loaded');
        $this->assertNotEmpty($startStep->transitions, 'Start step should have transitions');
        $transition = $startStep->transitions[0];
        $this->assertInstanceOf(TransitionDefinition::class, $transition);
        $this->assertSame('my_event', $transition->event);
        $this->assertSame('end', $transition->targetStepId);
    }
}
