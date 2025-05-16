<?php

declare(strict_types=1);

namespace Flowy\Tests\Action;

use Flowy\Action\ActionResolver;
use Flowy\Action\ActionInterface;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Context\WorkflowContext;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

class ActionResolverTest extends TestCase
{
    public function testResolvesCallableDirectly(): void
    {
        $callable = fn(WorkflowContext $context) => $context->set('called', true);
        $def = new ActionDefinition($callable);
        $resolver = new ActionResolver();
        $resolved = $resolver->resolve($def);
        $this->assertIsCallable($resolved);
        $context = new WorkflowContext([]);
        $resolved($context);
        $this->assertTrue($context->get('called'));
    }

    public function testResolvesFqcnDirectly(): void
    {
        $def = new ActionDefinition(DummyAction::class);
        $resolver = new ActionResolver();
        $resolved = $resolver->resolve($def);
        $this->assertInstanceOf(ActionInterface::class, $resolved);
        $context = new WorkflowContext([]);
        $resolved->execute($context);
        $this->assertTrue($context->get('executed'));
    }

    public function testResolvesServiceFromContainer(): void
    {
        $serviceId = 'my_action_service';
        $action = new DummyAction();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('has')->with($serviceId)->willReturn(true);
        $container->expects($this->once())
            ->method('get')->with($serviceId)->willReturn($action);
        $def = new ActionDefinition($serviceId);
        $resolver = new ActionResolver($container);
        $resolved = $resolver->resolve($def);
        $this->assertInstanceOf(ActionInterface::class, $resolved);
    }

    public function testThrowsIfServiceNotActionOrCallable(): void
    {
        $serviceId = 'bad_service';
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with($serviceId)->willReturn(true);
        $container->method('get')->with($serviceId)->willReturn(new \stdClass());
        $def = new ActionDefinition($serviceId);
        $resolver = new ActionResolver($container);
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($def);
    }

    public function testThrowsIfClassDoesNotImplementActionInterface(): void
    {
        $def = new ActionDefinition(\stdClass::class);
        $resolver = new ActionResolver();
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($def);
    }

    public function testThrowsIfIdentifierNotResolvable(): void
    {
        $def = new ActionDefinition('NonExistentClassOrService');
        $resolver = new ActionResolver();
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($def);
    }

    public function testThrowsIfIdentifierIsInvalidType(): void
    {
        $this->expectException(\TypeError::class);
        new ActionDefinition(42); // Not string or callable
    }
}

class DummyAction implements ActionInterface
{
    public function execute(WorkflowContext $context): void
    {
        $context->set('executed', true);
    }
}
