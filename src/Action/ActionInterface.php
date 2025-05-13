<?php

declare(strict_types=1);

namespace Flowy\Action;

use Flowy\Context\WorkflowContext;

/**
 * Interface for defining actions that can be executed within a workflow step.
 */
interface ActionInterface
{
    /**
     * Executes the action.
     *
     * The implementation should interact with the provided WorkflowContext to get input
     * and set output. If the action results in an error that should halt the workflow
     * or trigger a retry, it should throw an appropriate exception.
     *
     * For MVP, this method returns void. Future enhancements might allow returning
     * an ActionResult value object to convey more structured outcomes.
     *
     * @param WorkflowContext $context The current workflow context.
     * @return void
     * @throws \Throwable If an unrecoverable error occurs during action execution.
     */
    public function execute(WorkflowContext $context): void;
}
