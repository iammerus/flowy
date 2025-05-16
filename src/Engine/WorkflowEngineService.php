<?php

declare(strict_types=1);

namespace Flowy\Engine;

use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowInstanceIdInterface;
use Flowy\Model\WorkflowStatus;
use Flowy\Context\WorkflowContext;
use Flowy\Persistence\PersistenceInterface;
use Flowy\Registry\DefinitionRegistryInterface;
use Psr\Log\LoggerInterface;
use Flowy\Engine\WorkflowExecutorInterface;
use Flowy\Event\NullEventDispatcher;

/**
 * Default implementation of WorkflowEngineInterface.
 *
 * Handles instance lifecycle and delegates execution to WorkflowExecutor.
 */
final class WorkflowEngineService implements WorkflowEngineInterface
{
    public function __construct(
        private readonly WorkflowExecutorInterface $executor,
        private readonly PersistenceInterface $persistence,
        private readonly DefinitionRegistryInterface $definitionRegistry,
        private readonly LoggerInterface $logger,
        private readonly \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher = new NullEventDispatcher()
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
        $this->logger->info('Workflow instance started', [
            'workflow_id' => $workflowId,
            'instance_id' => (string)$instance->id,
            'version' => $definition->version,
            'business_key' => $businessKey,
        ]);
        $this->executor->proceed($instance->id);
        return $instance;
    }

    public function pause(WorkflowInstanceIdInterface $id): void
    {
        $instance = $this->getInstance($id);
        if ($instance && $instance->status === WorkflowStatus::RUNNING) {
            $instance->status = WorkflowStatus::PAUSED;
            $this->persistence->save($instance);
            $this->logger->info('Workflow instance paused', [
                'instance_id' => (string)$id,
                'workflow_id' => $instance->definitionId,
            ]);
            // Event dispatching for pause can be added in future if needed
        }
    }

    public function resume(WorkflowInstanceIdInterface $id): void
    {
        $instance = $this->getInstance($id);
        if ($instance && $instance->status === WorkflowStatus::PAUSED) {
            $instance->status = WorkflowStatus::RUNNING;
            $this->persistence->save($instance);
            $this->logger->info('Workflow instance resumed', [
                'instance_id' => (string)$id,
                'workflow_id' => $instance->definitionId,
            ]);
            $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowStartedEvent($instance));
            $this->executor->proceed($id);
        }
    }

    public function cancel(WorkflowInstanceIdInterface $id): void
    {
        $instance = $this->getInstance($id);
        if ($instance && !$instance->status->isTerminal()) {
            $instance->status = WorkflowStatus::CANCELLED;
            $this->persistence->save($instance);
            $this->logger->info('Workflow instance cancelled', [
                'instance_id' => (string)$id,
                'workflow_id' => $instance->definitionId,
            ]);
            $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowCancelledEvent($instance));
        }
    }

    public function retryFailedStep(WorkflowInstanceIdInterface $id): void
    {
        $instance = $this->getInstance($id);
        if ($instance && $instance->status === WorkflowStatus::FAILED) {
            $instance->retryAttempts = 0;
            $instance->status = WorkflowStatus::PENDING;
            $instance->errorDetails = null;
            $this->persistence->save($instance);
            $this->logger->info('Workflow instance retry initiated', [
                'instance_id' => (string)$id,
                'workflow_id' => $instance->definitionId,
            ]);
            $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowStartedEvent($instance));
            $this->executor->proceed($id);
        }
    }

    public function signal(WorkflowInstanceIdInterface $id, string $signalName, array $payload = []): void
    {
        $instance = $this->getInstance($id);
        if ($instance === null) {
            throw new \Flowy\Exception\DefinitionNotFoundException('Workflow instance not found: ' . (string)$id);
        }
        $instance->addSignal($signalName, $payload);
        $this->persistence->save($instance);
        $this->logger->info('Signal received for workflow instance', [
            'instance_id' => (string)$id,
            'workflow_id' => $instance->definitionId,
            'signal' => $signalName,
            'payload' => $payload,
        ]);
        // Event dispatching for signals can be added in future phases
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
