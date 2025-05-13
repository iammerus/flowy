<?php

declare(strict_types=1);

namespace Flowy\Condition;

use Flowy\Context\WorkflowContext;

/**
 * Interface for defining conditions that gate transitions between workflow steps.
 */
interface ConditionInterface
{
    /**
     * Evaluates the condition based on the current workflow context.
     *
     * @param WorkflowContext $context The current workflow context.
     * @return bool True if the condition is met, false otherwise.
     * @throws \Throwable If an error occurs during condition evaluation.
     */
    public function evaluate(WorkflowContext $context): bool;
}
