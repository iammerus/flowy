<?php

declare(strict_types=1);

namespace Flowy\Model;

/**
 * Interface for Workflow Instance Identifiers.
 *
 * Implementations of this interface are responsible for providing a unique identifier
 * for a workflow instance. This typically involves UUID generation and string representation.
 */
interface WorkflowInstanceIdInterface extends \Stringable
{
    /**
     * Returns the string representation of the identifier.
     *
     * @return string
     */
    public function toString(): string;

    /**
     * Checks if this identifier is equal to another identifier.
     *
     * @param WorkflowInstanceIdInterface $other
     * @return bool
     */
    public function equals(WorkflowInstanceIdInterface $other): bool;

    /**
     * Creates an instance of the identifier from a string representation.
     *
     * @param string $identifier
     * @return static
     */
    public static function fromString(string $identifier): static;

    /**
     * Generates a new unique identifier.
     *
     * @return static
     */
    public static function generate(): static;
}
