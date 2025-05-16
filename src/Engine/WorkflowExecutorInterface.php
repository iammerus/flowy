<?php

declare(strict_types=1);

namespace Flowy\Engine;

/**
 * Interface for workflow executor, to allow for test doubles and extensibility.
 */
interface WorkflowExecutorInterface
{
    /**
     * Proceeds execution of the given workflow instance.
     *
     * @param string|\Flowy\Model\WorkflowInstanceIdInterface $instanceId
     * @throws \Flowy\Exception\FlowyExceptionInterface On execution errors.
     */
    public function proceed($instanceId): void;
}
