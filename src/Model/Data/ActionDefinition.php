<?php

declare(strict_types=1);

namespace Flowy\Model\Data;

/**
 * Defines an action to be executed within a workflow step.
 */
class ActionDefinition
{
    /**
     * The identifier for the action.
     * This can be a Fully Qualified Class Name (FQCN) of an ActionInterface implementation,
     * a service ID (string) resolvable by a PSR-11 container, or a direct callable.
     *
     * @var string|callable
     */
    public readonly mixed $actionIdentifier;

    /**
     * @param string|callable $actionIdentifier The identifier for the action.
     *        This can be a Fully Qualified Class Name (FQCN) of an ActionInterface implementation,
     *        a service ID (string) resolvable by a PSR-11 container, or a direct callable.
     * @param array<string, mixed> $parameters Optional parameters to pass to the action (not used in MVP, for future use).
     * @param string|null $description An optional description for the action, useful for inline callables.
     */
    public function __construct(
        string|callable $actionIdentifier,
        public readonly array $parameters = [],
        public readonly ?string $description = null
    ) {
        $this->actionIdentifier = $actionIdentifier;
    }
}
