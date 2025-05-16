<?php

declare(strict_types=1);

namespace Flowy\Action;

use Flowy\Model\Data\ActionDefinition;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

/**
 * Resolves actions for workflow steps using PSR-11 container or direct callables.
 *
 * Supports FQCN, service ID, or callable as action identifiers.
 */
final class ActionResolver
{
    private ?ContainerInterface $container;

    /**
     * @param ContainerInterface|null $container Optional PSR-11 container for resolving services/classes.
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Resolves an ActionDefinition to a callable or ActionInterface instance.
     *
     * @param ActionDefinition $actionDef
     * @return callable|ActionInterface
     * @throws InvalidArgumentException If the action cannot be resolved.
     */
    public function resolve(ActionDefinition $actionDef): callable|ActionInterface
    {
        $identifier = $actionDef->actionIdentifier;

        // If it's already a callable, return as-is
        if (is_callable($identifier)) {
            return $identifier;
        }

        // If it's a string, treat as FQCN or service ID
        if (is_string($identifier)) {
            // Try container first if available
            if ($this->container && $this->container->has($identifier)) {
                $service = $this->container->get($identifier);
                if ($service instanceof ActionInterface || is_callable($service)) {
                    return $service;
                }
                throw new InvalidArgumentException("Service '$identifier' does not implement ActionInterface or is not callable.");
            }
            // If no container or not found, try to instantiate FQCN directly
            if (class_exists($identifier)) {
                $instance = new $identifier();
                if ($instance instanceof ActionInterface) {
                    return $instance;
                }
                throw new InvalidArgumentException("Class '$identifier' does not implement ActionInterface.");
            }
            throw new InvalidArgumentException("Action identifier '$identifier' could not be resolved as a service or class.");
        }

        throw new InvalidArgumentException('ActionDefinition identifier must be a string (FQCN/service ID) or callable.');
    }
}
