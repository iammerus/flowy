<?php

declare(strict_types=1);

namespace Flowy\Condition;

use Flowy\Model\Data\TransitionDefinition;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

/**
 * Resolves conditions for workflow transitions using PSR-11 container or FQCN.
 *
 * Supports FQCN, service ID, or null as condition identifiers.
 */
final class ConditionResolver
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
     * Resolves a TransitionDefinition to a ConditionInterface instance, or null if no condition.
     *
     * @param TransitionDefinition $transDef
     * @return ConditionInterface|null
     * @throws InvalidArgumentException If the condition cannot be resolved.
     */
    public function resolve(TransitionDefinition $transDef): ?ConditionInterface
    {
        $identifier = $transDef->conditionIdentifier;
        if ($identifier === null) {
            return null;
        }

        // Try container first if available
        if ($this->container && $this->container->has($identifier)) {
            $service = $this->container->get($identifier);
            if ($service instanceof ConditionInterface) {
                return $service;
            }
            throw new InvalidArgumentException("Service '$identifier' does not implement ConditionInterface.");
        }
        // If no container or not found, try to instantiate FQCN directly
        if (class_exists($identifier)) {
            $instance = new $identifier();
            if ($instance instanceof ConditionInterface) {
                return $instance;
            }
            throw new InvalidArgumentException("Class '$identifier' does not implement ConditionInterface.");
        }
        throw new InvalidArgumentException("Condition identifier '$identifier' could not be resolved as a service or class.");
    }
}
