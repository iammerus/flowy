<?php

declare(strict_types=1);

namespace Flowy\Engine;

use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;

/**
 * Interface for the main workflow engine service (MVP).
 *
 * Provides methods to start, pause, resume, cancel, and query workflow instances.
 */
interface WorkflowEngineInterface
{
    /**
     * Starts a new workflow instance.
     *
     * @param string $workflowId
     * @param string|null $version
     * @param WorkflowContext|null $context
     * @param string|null $businessKey
     * @return WorkflowInstance
     */
    public function start(string $workflowId, ?string $version = null, ?WorkflowContext $context = null, ?string $businessKey = null): WorkflowInstance;

    /**
     * Pauses a running workflow instance.
     *
     * @param WorkflowInstanceIdInterface $id
     * @return void
     */
    public function pause(WorkflowInstanceIdInterface $id): void;

    /**
     * Resumes a paused workflow instance.
     *
     * @param WorkflowInstanceIdInterface $id
     * @return void
     */
    public function resume(WorkflowInstanceIdInterface $id): void;

    /**
     * Cancels a workflow instance.
     *
     * @param WorkflowInstanceIdInterface $id
     * @return void
     */
    public function cancel(WorkflowInstanceIdInterface $id): void;

    /**
     * Retrieves a workflow instance by ID.
     *
     * @param WorkflowInstanceIdInterface $id
     * @return WorkflowInstance|null
     */
    public function getInstance(WorkflowInstanceIdInterface $id): ?WorkflowInstance;

    /**
     * Finds workflow instances by status.
     *
     * @param WorkflowStatus $status
     * @param string|null $workflowDefinitionId
     * @param int|null $limit
     * @return WorkflowInstance[]
     */
    public function findInstancesByStatus(WorkflowStatus $status, ?string $workflowDefinitionId = null, ?int $limit = null): array;

    /**
     * Retries a failed workflow instance from its current step.
     *
     * Resets retry attempts, sets status to PENDING, clears error details, and resumes execution.
     *
     * @param WorkflowInstanceIdInterface $id
     * @return void
     */
    public function retryFailedStep(WorkflowInstanceIdInterface $id): void;

    /**
     * Sends a signal to a running workflow instance.
     *
     * @param WorkflowInstanceIdInterface $id
     * @param string $signalName
     * @param array $payload
     * @return void
     */
    public function signal(WorkflowInstanceIdInterface $id, string $signalName, array $payload = []): void;
}
