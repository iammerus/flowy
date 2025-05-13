<?php

declare(strict_types=1);

namespace Flowy\Model\Data;

/**
 * Defines a transition between workflow steps.
 */
class TransitionDefinition
{
    /**
     * The identifier of the target step for this transition.
     */
    public readonly string $targetStepId;

    /**
     * The identifier for the condition to be evaluated for this transition.
     * This can be a Fully Qualified Class Name (FQCN) of a ConditionInterface implementation,
     * a service ID (string) resolvable by a PSR-11 container, or null if no condition is required.
     * In Phase 2, this could also be an expression string.
     *
     * @var string|null
     */
    public readonly ?string $conditionIdentifier;

    /**
     * The type of condition. For MVP, this will primarily be 'class' or 'service'.
     * Future phases might include 'expression' for Symfony ExpressionLanguage.
     *
     * @var string|null
     */
    public readonly ?string $conditionType;

    /**
     * Optional parameters for the condition (Not used in MVP, for future use).
     *
     * @var array<string, mixed>
     */
    public readonly array $conditionParameters;

    /**
     * For event-driven transitions (Phase 2+). The name of the event that can trigger this transition.
     */
    public readonly ?string $event;

    /**
     * @param string $targetStepId The identifier of the target step.
     * @param string|null $conditionIdentifier The identifier for the condition.
     * @param string|null $conditionType The type of condition ('class', 'service', etc.).
     * @param array<string, mixed> $conditionParameters Parameters for the condition.
     * @param string|null $event The event name that can trigger this transition.
     */
    public function __construct(
        string $targetStepId,
        ?string $conditionIdentifier = null,
        ?string $conditionType = null, // For MVP, this might be implicitly 'class' or 'service_id'
        array $conditionParameters = [],
        ?string $event = null // Primarily for Phase 2+
    ) {
        $this->targetStepId = $targetStepId;
        $this->conditionIdentifier = $conditionIdentifier;
        $this->conditionType = $conditionType;
        $this->conditionParameters = $conditionParameters;
        $this->event = $event;

        if ($this->conditionIdentifier !== null && $this->conditionType === null) {
            // Basic inference for MVP if not explicitly set
            // This could be refined or made stricter based on loader logic
            if (class_exists($this->conditionIdentifier)) {
                $this->conditionType = 'class';
            } elseif (is_string($this->conditionIdentifier) && !empty($this->conditionIdentifier)) {
                // Assume service ID if not a class and not empty.
                // This is a simplification; actual resolution happens in ConditionResolver.
                $this->conditionType = 'service';
            }
        }
    }
}
