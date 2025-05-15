<?php

declare(strict_types=1);

namespace Flowy\Engine;

use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;
use Flowy\Persistence\PersistenceInterface;
use Flowy\Registry\DefinitionRegistryInterface;

/**
 * Default implementation of WorkflowEngineInterface.
 *
 * Handles instance lifecycle and delegates execution to WorkflowExecutor.
 */
final class WorkflowEngineService implements WorkflowEngineInterface
{
    public function __construct(
        private readonly WorkflowExecutor $executor,
        private readonly PersistenceInterface $persistence,
        private readonly DefinitionRegistryInterface $definitionRegistry
    ) {}

    public function start(string $workflowId, ?string $version = null, ?WorkflowContext $context = null, ?string $businessKey = null): WorkflowInstance
    {
        $definition = $this->definitionRegistry->getDefinition($workflowId, $version);
        $instance = new WorkflowInstance(
            $definition->id::generate(),
            $definition->id,
            $definition->version,
            WorkflowStatus::PENDING,
            $context ?? new WorkflowContext([]),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            $businessKey,
            null,
            [],
            null,
            0,
            1
        );
        $this->persistence->save($instance);
        $this->executor->proceed($instance->id);
        return $instance;
    }

    public function pause(WorkflowInstanceIdInterface $id): void
    {
        $instance = $this->getInstance($id);
        if ($instance && $instance->status === WorkflowStatus::RUNNING) {
            $instance->status = WorkflowStatus::PAUSED;
            $this->persistence->save($instance);
        }
    }

    public function resume(WorkflowInstanceIdInterface $id): void
    {
        $instance = $this->getInstance($id);
        if ($instance && $instance->status === WorkflowStatus::PAUSED) {
            $instance->status = WorkflowStatus::RUNNING;
            $this->persistence->save($instance);
            $this->executor->proceed($id);
        }
    }

    public function cancel(WorkflowInstanceIdInterface $id): void
    {
        $instance = $this->getInstance($id);
        if ($instance && !$instance->status->isTerminal()) {
            $instance->status = WorkflowStatus::CANCELLED;
            $this->persistence->save($instance);
        }
    }

    public function getInstance(WorkflowInstanceIdInterface $id): ?WorkflowInstance
    {
        return $this->persistence->find($id);
    }

    public function findInstancesByStatus(WorkflowStatus $status, ?string $workflowDefinitionId = null, ?int $limit = null): array
    {
        return $this->persistence->findInstancesByStatus($status, $workflowDefinitionId, $limit);
    }
}
