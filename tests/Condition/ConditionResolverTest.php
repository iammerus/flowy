<?php

declare(strict_types=1);

namespace Flowy\Tests\Condition;

use Flowy\Condition\ConditionResolver;
use Flowy\Condition\ConditionInterface;
use Flowy\Model\Data\TransitionDefinition;
use Flowy\Context\WorkflowContext;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

class ConditionResolverTest extends TestCase
{
    public function testResolvesNullIfNoCondition(): void
    {
        $def = new TransitionDefinition('target');
        $resolver = new ConditionResolver();
        $this->assertNull($resolver->resolve($def));
    }

    public function testResolvesFqcnDirectly(): void
    {
        $def = new TransitionDefinition('target', DummyCondition::class);
        $resolver = new ConditionResolver();
        $resolved = $resolver->resolve($def);
        $this->assertInstanceOf(ConditionInterface::class, $resolved);
        $context = new WorkflowContext(['pass' => true]);
        $this->assertTrue($resolved->evaluate($context));
    }

    public function testResolvesServiceFromContainer(): void
    {
        $serviceId = 'my_condition_service';
        $condition = new DummyCondition();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('has')->with($serviceId)->willReturn(true);
        $container->expects($this->once())
            ->method('get')->with($serviceId)->willReturn($condition);
        $def = new TransitionDefinition('target', $serviceId);
        $resolver = new ConditionResolver($container);
        $resolved = $resolver->resolve($def);
        $this->assertInstanceOf(ConditionInterface::class, $resolved);
    }

    public function testThrowsIfServiceNotConditionInterface(): void
    {
        $serviceId = 'bad_service';
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with($serviceId)->willReturn(true);
        $container->method('get')->with($serviceId)->willReturn(new \stdClass());
        $def = new TransitionDefinition('target', $serviceId);
        $resolver = new ConditionResolver($container);
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($def);
    }

    public function testThrowsIfClassDoesNotImplementConditionInterface(): void
    {
        $def = new TransitionDefinition('target', \stdClass::class);
        $resolver = new ConditionResolver();
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($def);
    }

    public function testThrowsIfIdentifierNotResolvable(): void
    {
        $def = new TransitionDefinition('target', 'NonExistentClassOrService');
        $resolver = new ConditionResolver();
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($def);
    }
}

class DummyCondition implements ConditionInterface
{
    public function evaluate(WorkflowContext $context): bool
    {
        return (bool) $context->get('pass', true);
    }
}
