<?php

namespace Flowy\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Flowy\Workflow\WorkflowInstance;
use Flowy\Persistence\WorkflowInstanceRepositoryInterface;

class DoctrinePersistenceAdapter implements WorkflowInstanceRepositoryInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function save(WorkflowInstance $instance): void
    {
        $entityClass = \Flowy\Persistence\Doctrine\Entity\WorkflowInstance::class;
        $entity = $this->em->find($entityClass, (string) $instance->id);
        if (!$entity) {
            $entity = new $entityClass();
            $entity->setId((string) $instance->id);
            $entity->setCreatedAt($instance->createdAt);
        }
        $entity->setWorkflowDefinitionId($instance->definitionId);
        $entity->setWorkflowDefinitionVersion($instance->definitionVersion);
        $entity->setStatus($instance->status);
        $entity->setContext($instance->context->all());
        $entity->setHistory($instance->history);
        $entity->setUpdatedAt($instance->updatedAt);
        $entity->setError($instance->errorDetails);
        $entity->setBusinessKey($instance->businessKey);
        $entity->setCurrentStepId($instance->currentStepId);
        $entity->setRetryCount($instance->retryAttempts);
        $entity->setNextExecutionAt($instance->scheduledAt);
        // Version is handled by Doctrine
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function find(\Flowy\Model\WorkflowInstanceIdInterface $instanceId): ?\Flowy\Model\WorkflowInstance
    {
        $entityClass = \Flowy\Persistence\Doctrine\Entity\WorkflowInstance::class;
        $entity = $this->em->find($entityClass, (string) $instanceId);
        if (!$entity) {
            return null;
        }
        return $this->mapEntityToDomain($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function findByBusinessKey(string $workflowDefinitionId, string $businessKey): ?\Flowy\Model\WorkflowInstance
    {
        $entityClass = \Flowy\Persistence\Doctrine\Entity\WorkflowInstance::class;
        $entity = $this->em->getRepository($entityClass)->findOneBy([
            'workflowDefinitionId' => $workflowDefinitionId,
            'businessKey' => $businessKey,
        ]);
        if (!$entity) {
            return null;
        }
        return $this->mapEntityToDomain($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function findInstancesByStatus(
        \Flowy\Model\WorkflowStatus $status,
        ?string $workflowDefinitionId = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $entityClass = \Flowy\Persistence\Doctrine\Entity\WorkflowInstance::class;
        $criteria = ['status' => $status];
        if ($workflowDefinitionId !== null) {
            $criteria['workflowDefinitionId'] = $workflowDefinitionId;
        }
        $entities = $this->em->getRepository($entityClass)->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
        return array_map(fn($entity) => $this->mapEntityToDomain($entity), $entities);
    }

    /**
     * Find workflow instances due for processing (e.g., polling worker).
     *
     * @param int $limit
     * @return array<\Flowy\Model\WorkflowInstance>
     */
    public function findDueForProcessing(int $limit = 50): array
    {
        $entityClass = \Flowy\Persistence\Doctrine\Entity\WorkflowInstance::class;
        $qb = $this->em->createQueryBuilder();
        $qb->select('wi')
            ->from($entityClass, 'wi')
            ->where('wi.status IN (:statuses)')
            ->andWhere('wi.nextExecutionAt IS NULL OR wi.nextExecutionAt <= :now')
            ->setParameter('statuses', [
                \Flowy\Model\WorkflowStatus::PENDING,
                \Flowy\Model\WorkflowStatus::RUNNING,
            ])
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('wi.nextExecutionAt', 'ASC')
            ->setMaxResults($limit);
        $entities = $qb->getQuery()->getResult();
        return array_map(fn($entity) => $this->mapEntityToDomain($entity), $entities);
    }

    /**
     * {@inheritdoc}
     */
    public function findFailed(
        int $limit = 50,
        int $maxRetryAttempts = 3,
        ?string $workflowDefinitionId = null
    ): array {
        $entityClass = \Flowy\Persistence\Doctrine\Entity\WorkflowInstance::class;
        $qb = $this->em->createQueryBuilder();
        $qb->select('wi')
            ->from($entityClass, 'wi')
            ->where('wi.status = :status')
            ->andWhere('wi.retryCount < :maxRetries')
            ->setParameter('status', \Flowy\Model\WorkflowStatus::FAILED)
            ->setParameter('maxRetries', $maxRetryAttempts)
            ->orderBy('wi.updatedAt', 'ASC') // Process older failures first
            ->setMaxResults($limit);

        if ($workflowDefinitionId !== null) {
            $qb->andWhere('wi.workflowDefinitionId = :defId')
               ->setParameter('defId', $workflowDefinitionId);
        }

        $entities = $qb->getQuery()->getResult();
        return array_map(fn($entity) => $this->mapEntityToDomain($entity), $entities);
    }

    /**
     * Map a Doctrine entity to a domain WorkflowInstance.
     *
     * @param \Flowy\Persistence\Doctrine\Entity\WorkflowInstance $entity
     * @return \Flowy\Model\WorkflowInstance
     */
    private function mapEntityToDomain(\Flowy\Persistence\Doctrine\Entity\WorkflowInstance $entity): \Flowy\Model\WorkflowInstance
    {
        $idClass = \Flowy\Model\WorkflowInstanceIdInterface::class;
        $contextClass = \Flowy\Context\WorkflowContext::class;
        $id = new $idClass($entity->getId());
        $context = $contextClass::fromArray($entity->getContext());
        return new \Flowy\Model\WorkflowInstance(
            $id,
            $entity->getWorkflowDefinitionId(),
            $entity->getWorkflowDefinitionVersion(),
            $entity->getStatus(),
            $context,
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
            $entity->getBusinessKey(),
            $entity->getCurrentStepId(),
            $entity->getHistory(),
            $entity->getError(),
            $entity->getRetryCount(),
            $entity->getVersion(),
            // lockedBy, lockExpiresAt, scheduledAt are not mapped in MVP entity
            null,
            null,
            $entity->getNextExecutionAt()
        );
    }
}