<?php

declare(strict_types=1);

namespace Flowy\Persistence;

use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;

/**
 * Interface PersistenceInterface
 *
 * Defines the contract for persisting and retrieving workflow instances.
 */
interface PersistenceInterface
{
    /**
     * Saves (creates or updates) a workflow instance.
     *
     * @param WorkflowInstance $instance The workflow instance to save.
     * @throws \Flowy\Exception\PersistenceException If saving fails.
     */
    public function save(WorkflowInstance $instance): void;

    /**
     * Finds a workflow instance by its unique ID.
     *
     * @param WorkflowInstanceIdInterface $instanceId The ID of the workflow instance.
     * @return WorkflowInstance|null The found workflow instance, or null if not found.
     * @throws \Flowy\Exception\PersistenceException If retrieval fails.
     */
    public function find(WorkflowInstanceIdInterface $instanceId): ?WorkflowInstance;

    /**
     * Finds a workflow instance by its definition ID and a business key.
     *
     * The business key is a user-defined identifier that is unique for a given workflow definition.
     *
     * @param string $workflowDefinitionId The ID of the workflow definition.
     * @param string $businessKey The business key.
     * @return WorkflowInstance|null The found workflow instance, or null if not found.
     * @throws \Flowy\Exception\PersistenceException If retrieval fails.
     */
    public function findByBusinessKey(string $workflowDefinitionId, string $businessKey): ?WorkflowInstance;

    /**
     * Finds workflow instances by their status.
     *
     * @param WorkflowStatus $status The status to filter by.
     * @param string|null $workflowDefinitionId Optional. Filter by a specific workflow definition ID.
     * @param int $limit The maximum number of instances to return.
     * @param int $offset The number of instances to skip (for pagination).
     * @return array<WorkflowInstance> An array of workflow instances, potentially empty.
     * @throws \Flowy\Exception\PersistenceException If retrieval fails.
     */
    public function findInstancesByStatus(
        WorkflowStatus $status,
        ?string $workflowDefinitionId = null,
        int $limit = 50,
        int $offset = 0
    ): array;

    /**
     * Finds failed workflow instances that have not exceeded the maximum retry attempts.
     *
     * @param int $limit The maximum number of instances to return.
     * @param int $maxRetryAttempts The maximum number of retry attempts allowed.
     * @param string|null $workflowDefinitionId Optional. Filter by a specific workflow definition ID.
     * @return array<WorkflowInstance> An array of failed workflow instances, potentially empty.
     * @throws \Flowy\Exception\PersistenceException If retrieval fails.
     */
    public function findFailed(
        int $limit = 50,
        int $maxRetryAttempts = 3,
        ?string $workflowDefinitionId = null
    ): array;
}
