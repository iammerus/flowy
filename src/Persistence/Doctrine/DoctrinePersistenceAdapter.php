<?php

declare(strict_types=1);

namespace Flowy\Persistence\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface; // Keep this if other methods will use it directly
use Flowy\Model\WorkflowStatus; // Keep this if other methods will use it directly
use Flowy\Persistence\PersistenceInterface;

/**
 * Doctrine ORM implementation of the PersistenceInterface.
 *
 * Responsible for saving, finding, and managing WorkflowInstance entities
 * using Doctrine ORM.
 */
final class DoctrinePersistenceAdapter implements PersistenceInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Saves the workflow instance to the persistence layer.
     *
     * This method ensures the `updatedAt` timestamp is set to the current time before persisting.
     * It relies on Doctrine's built-in optimistic locking via the `version` property
     * of the WorkflowInstance entity.
     *
     * @param WorkflowInstance $instance The workflow instance to save.
     * @throws OptimisticLockException If a concurrency conflict occurs during save.
     * @throws \Throwable If any other persistence error occurs.
     */
    public function save(WorkflowInstance $instance): void
    {
        // Ensure the updatedAt timestamp is current for this save operation.
        $instance->updatedAt = new DateTimeImmutable();

        try {
            // If the entity is already managed, persist will be a no-op for changes,
            // but it's good practice to call it if the entity might be new or detached.
            // Doctrine tracks changes on managed entities, and flush will write them.
            $this->entityManager->persist($instance);
            $this->entityManager->flush(); // Flush can be called here or at a higher level (Unit of Work pattern)
        } catch (OptimisticLockException $e) {
            // Rethrow OptimisticLockException to be handled by the caller.
            // Consider wrapping in a domain-specific exception in the future e.g., PersistenceConflictException
            throw $e;
        } catch (\Exception $e) {
            // Rethrow other persistence-related exceptions.
            // Consider wrapping in a domain-specific exception e.g., PersistenceOperationFailedException
            // For now, rethrow the original exception to provide full context.
            // Example: throw new \Flowy\Exception\PersistenceOperationFailedException(
            //    sprintf("Failed to save workflow instance %s", $instance->id->toString()), 0, $e
            // );
            throw $e;
        }
    }

    /**
     * Finds a workflow instance by its unique identifier.
     *
     * The method leverages the custom Doctrine type `WorkflowInstanceIdType`
     * for handling the `WorkflowInstanceIdInterface` automatically.
     *
     * @param WorkflowInstanceIdInterface $instanceId The unique identifier of the workflow instance.
     * @return WorkflowInstance|null The found workflow instance, or null if not found.
     * @throws \Throwable If any persistence error occurs.
     */
    public function find(WorkflowInstanceIdInterface $instanceId): ?WorkflowInstance
    {
        try {
            // Doctrine's find method can directly use the WorkflowInstanceIdInterface object
            // because we've mapped it with a custom type (WorkflowInstanceIdType).
            // The custom type handles the conversion to the appropriate database value.
            $instance = $this->entityManager->find(WorkflowInstance::class, $instanceId);

            // Ensure the found object is indeed a WorkflowInstance or null.
            if ($instance !== null && !$instance instanceof WorkflowInstance) {
                // This case should ideally not happen if mappings are correct.
                // For robustness, explicitly return null or throw a specific exception.
                // Log an error if this unexpected scenario occurs.
                // error_log("entityManager->find returned an unexpected type for WorkflowInstance.");
                return null;
            }

            return $instance;
        } catch (\Exception $e) {
            // Wrap generic exceptions in a domain-specific persistence exception if desired.
            // For example: throw new \Flowy\Exception\PersistenceOperationFailedException(
            //    sprintf("Failed to find workflow instance with ID %s", $instanceId->toString()), 0, $e
            // );
            throw $e; // Rethrow for now
        }
    }

    /**
     * Finds a workflow instance by its definition ID and business key.
     *
     * @param string $definitionId The identifier of the workflow definition.
     * @param string $businessKey The business key associated with the workflow instance.
     * @return WorkflowInstance|null The found workflow instance, or null if not found.
     * @throws \Throwable If any persistence error occurs.
     */
    public function findByBusinessKey(string $definitionId, string $businessKey): ?WorkflowInstance
    {
        try {
            $repository = $this->entityManager->getRepository(WorkflowInstance::class);
            $instance = $repository->findOneBy([
                'definitionId' => $definitionId,
                'businessKey' => $businessKey,
            ]);

            // Ensure the found object is indeed a WorkflowInstance or null.
            if ($instance !== null && !$instance instanceof WorkflowInstance) {
                // This case should ideally not happen if mappings are correct.
                // For robustness, explicitly return null or throw a specific exception.
                // Log an error if this unexpected scenario occurs.
                // error_log("Repository->findOneBy returned an unexpected type for WorkflowInstance.");
                return null;
            }

            return $instance;
        } catch (\Exception $e) {
            // Wrap generic exceptions in a domain-specific persistence exception if desired.
            // For example: throw new \Flowy\Exception\PersistenceOperationFailedException(
            //    sprintf("Failed to find workflow instance with definition ID %s and business key %s", $definitionId, $businessKey), 0, $e
            // );
            throw $e; // Rethrow for now
        }
    }

    /**
     * {@inheritdoc}
     * Finds workflow instances that are due for processing.
     *
     * This includes instances that are PENDING (and scheduled for now or in the past, or not scheduled),
     * or RUNNING and scheduled for now or in the past (e.g., for delayed retries).
     *
     * Results are ordered by scheduledAt (earliest first, with NULLs typically treated as earliest by some DBs or needing explicit handling),
     * then by createdAt (oldest first).
     */
    public function findDueForProcessing(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        try {
            $now = new DateTimeImmutable();
            $queryBuilder = $this->entityManager->getRepository(WorkflowInstance::class)->createQueryBuilder('wi');

            $queryBuilder
                ->where(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->andX(
                            $queryBuilder->expr()->eq('wi.status', ':status_pending'),
                            $queryBuilder->expr()->orX(
                                $queryBuilder->expr()->isNull('wi.scheduledAt'),
                                $queryBuilder->expr()->lte('wi.scheduledAt', ':now')
                            )
                        ),
                        $queryBuilder->expr()->andX(
                            $queryBuilder->expr()->eq('wi.status', ':status_running'),
                            $queryBuilder->expr()->isNotNull('wi.scheduledAt'),
                            $queryBuilder->expr()->lte('wi.scheduledAt', ':now')
                        )
                    )
                )
                ->setParameter('status_pending', WorkflowStatus::PENDING)
                ->setParameter('status_running', WorkflowStatus::RUNNING)
                ->setParameter('now', $now)
                ->orderBy('wi.scheduledAt', 'ASC') // Consider NULLS FIRST/LAST if DB specific behavior is an issue
                ->addOrderBy('wi.createdAt', 'ASC')
                ->setMaxResults($limit);

            return $queryBuilder->getQuery()->getResult();
        } catch (\Exception $e) {
            // Wrap generic exceptions in a domain-specific persistence exception if desired.
            // For example: throw new \Flowy\Exception\PersistenceQueryFailedException(
            //    "Failed to find workflow instances due for processing", 0, $e
            // );
            throw $e; // Rethrow for now
        }
    }

    /**
     * {@inheritdoc}
     * Finds workflow instances by their status.
     *
     * Results are ordered by creation date (oldest first) by default.
     *
     * @param WorkflowStatus $status The status to filter by.
     * @param string|null $workflowDefinitionId Optional. Filter by a specific workflow definition ID.
     * @param int $limit Maximum number of results to return.
     * @param int $offset Starting offset for results.
     * @return array<WorkflowInstance> Array of matching WorkflowInstance objects.
     */
    public function findInstancesByStatus(
        WorkflowStatus $status,
        ?string $workflowDefinitionId = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        if ($limit <= 0) {
            return [];
        }

        try {
            $repository = $this->entityManager->getRepository(WorkflowInstance::class);
            $criteria = ['status' => $status];
            
            // Add workflow definition ID to criteria if provided
            if ($workflowDefinitionId !== null) {
                $criteria['definitionId'] = $workflowDefinitionId;
            }
            
            return $repository->findBy(
                $criteria,
                ['createdAt' => 'ASC'], // Order by creation date, oldest first
                $limit,
                $offset
            );
        } catch (\Exception $e) {
            // Wrap generic exceptions in a domain-specific persistence exception if desired.
            // For example: throw new \Flowy\Exception\PersistenceQueryFailedException(
            //    sprintf("Failed to find workflow instances with status %s", $status->value), 0, $e
            // );
            throw $e; // Rethrow for now
        }
    }

    /**
     * {@inheritdoc}
     * Finds failed workflow instances.
     *
     * @param int $limit Maximum number of results to return.
     * @param int $maxRetryAttempts Only include instances that have fewer retry attempts than this value.
     * @param string|null $workflowDefinitionId Optional. Filter by workflow definition ID.
     * @return array<WorkflowInstance> Array of failed workflow instances.
     * @throws \Throwable If any persistence error occurs.
     */
    public function findFailed(int $limit = 50, int $maxRetryAttempts = 3, ?string $workflowDefinitionId = null): array
    {
        if ($limit <= 0) {
            return [];
        }

        try {
            $queryBuilder = $this->entityManager->getRepository(WorkflowInstance::class)->createQueryBuilder('wi');
            
            $queryBuilder->where('wi.status = :status_failed')
                ->andWhere('wi.retryAttempts < :max_retry_attempts')
                ->setParameter('status_failed', WorkflowStatus::FAILED)
                ->setParameter('max_retry_attempts', $maxRetryAttempts);
            
            // Add workflow definition ID filter if provided
            if ($workflowDefinitionId !== null) {
                $queryBuilder->andWhere('wi.definitionId = :definition_id')
                    ->setParameter('definition_id', $workflowDefinitionId);
            }
            
            $queryBuilder->orderBy('wi.updatedAt', 'DESC')
                ->setMaxResults($limit);
            
            return $queryBuilder->getQuery()->getResult();
        } catch (\Exception $e) {
            // Wrap generic exceptions in a domain-specific persistence exception if desired.
            // For example: throw new \Flowy\Exception\PersistenceQueryFailedException(
            //    "Failed to find failed workflow instances", 0, $e
            // );
            throw $e; // Rethrow for now
        }
    }
}