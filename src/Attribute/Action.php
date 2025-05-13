<?php

declare(strict_types=1);

namespace Flowy\Attribute;

use Attribute;

/**
 * Attribute to define an Action associated with a Step.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Action
{
    /**
     * @param string|null $service The FQCN or service ID of an ActionInterface implementation.
     * @param callable|null $callable A direct callable to be executed as the action.
     * @param string|null $description An optional description, especially useful for callable actions.
     * @param array<string, mixed> $parameters Optional parameters for the action (Not used in MVP).
     */
    public function __construct(
        public readonly ?string $service = null,
        public readonly mixed $callable = null, // mixed to allow callables
        public readonly ?string $description = null,
        public readonly array $parameters = []
    ) {
        if ($this->service === null && $this->callable === null) {
            throw new \InvalidArgumentException('Action attribute must have either a service or a callable defined.');
        }
        if ($this->service !== null && $this->callable !== null) {
            throw new \InvalidArgumentException('Action attribute cannot have both a service and a callable defined.');
        }
    }

    public function getActionIdentifier(): string|callable
    {
        return $this->service ?? $this->callable;
    }
}
