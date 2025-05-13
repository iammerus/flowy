<?php

declare(strict_types=1);

namespace Flowy\Model\Data;

/**
 * Defines a step within a workflow.
 */
class StepDefinition
{
    /**
     * Unique identifier for the step within the workflow definition.
     */
    public readonly string $id;

    /**
     * Optional human-readable name for the step.
     */
    public readonly ?string $name;

    /**
     * Optional description of the step's purpose.
     */
    public readonly ?string $description;

    /**
     * Indicates if this is the initial step of the workflow.
     */
    public readonly bool $isInitial;

    /**
     * The type of the step (e.g., 'action', 'fork', 'join', 'callActivity').
     * For MVP, this will typically be a simple action step.
     */
    public readonly string $type;

    /**
     * Policy for retrying actions within this step upon failure.
     * For MVP, this supports 'attempts' and 'fixedDelaySeconds'.
     */
    public readonly ?RetryPolicy $retryPolicy;

    /**
     * Optional timeout duration for the step (e.g., "PT5M" for 5 minutes).
     * Not strictly enforced in MVP execution but defined for future use.
     */
    public readonly ?string $timeoutDuration; // ISO 8601 duration format

    /**
     * Array of actions to be executed when this step is entered.
     *
     * @var ActionDefinition[]
     */
    public readonly array $actions;

    /**
     * Array of possible transitions from this step to others.
     *
     * @var TransitionDefinition[]
     */
    public readonly array $transitions;

    /**
     * @param string $id Unique identifier for the step.
     * @param ActionDefinition[] $actions Actions to execute.
     * @param TransitionDefinition[] $transitions Possible transitions.
     * @param string|null $name Optional name.
     * @param string|null $description Optional description.
     * @param bool $isInitial Whether this is the initial step (default: false).
     * @param string $type Type of step (default: 'action').
     * @param RetryPolicy|null $retryPolicy Retry policy for the step.
     * @param string|null $timeoutDuration Timeout duration for the step.
     */
    public function __construct(
        string $id,
        array $actions = [],
        array $transitions = [],
        ?string $name = null,
        ?string $description = null,
        bool $isInitial = false,
        string $type = 'action', // Default to 'action' for MVP
        ?RetryPolicy $retryPolicy = null,
        ?string $timeoutDuration = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->isInitial = $isInitial;
        $this->type = $type;
        $this->retryPolicy = $retryPolicy;
        $this->timeoutDuration = $timeoutDuration;

        // Ensure actions are ActionDefinition instances
        foreach ($actions as $action) {
            if (! $action instanceof ActionDefinition) {
                throw new \InvalidArgumentException('All actions must be instances of ActionDefinition.');
            }
        }
        $this->actions = $actions;

        // Ensure transitions are TransitionDefinition instances
        foreach ($transitions as $transition) {
            if (! $transition instanceof TransitionDefinition) {
                throw new \InvalidArgumentException('All transitions must be instances of TransitionDefinition.');
            }
        }
        $this->transitions = $transitions;
    }
}
