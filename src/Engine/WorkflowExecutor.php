<?php

declare(strict_types=1);

namespace Flowy\Engine;

use Flowy\Persistence\PersistenceInterface;
use Flowy\Action\ActionResolver;
use Flowy\Condition\ConditionResolver;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Flowy\Registry\DefinitionRegistryInterface;

/**
 * Core workflow executor responsible for driving workflow instance execution.
 *
 * Handles step/action execution, transition evaluation, event dispatching, and status updates.
 * Designed for extensibility and testability.
 */
final class WorkflowExecutor implements WorkflowExecutorInterface
{
    private PersistenceInterface $persistence;
    private ActionResolver $actionResolver;
    private ConditionResolver $conditionResolver;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private DefinitionRegistryInterface $definitionRegistry;

    public function __construct(
        PersistenceInterface $persistence,
        ActionResolver $actionResolver,
        ConditionResolver $conditionResolver,
        DefinitionRegistryInterface $definitionRegistry,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->persistence = $persistence;
        $this->actionResolver = $actionResolver;
        $this->conditionResolver = $conditionResolver;
        $this->definitionRegistry = $definitionRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Proceeds execution of the given workflow instance.
     *
     * Loads the instance, executes actions, evaluates transitions, updates status, and dispatches events.
     *
     * @param string|\Flowy\Model\WorkflowInstanceIdInterface $instanceId
     * @throws \Flowy\Exception\FlowyExceptionInterface On execution errors.
     */
    public function proceed($instanceId): void
    {
        try {
            // 1. Load the workflow instance
            $instance = $this->persistence->find($instanceId);
            if ($instance === null) {
                $this->logger->error('Workflow instance not found', [
                    'instance_id' => (string)$instanceId
                ]);
                throw new \Flowy\Exception\DefinitionNotFoundException(
                    'Workflow instance not found: ' . (string)$instanceId
                );
            }

            // 2. Load the workflow definition
            $definition = $this->definitionRegistry->getDefinition(
                $instance->definitionId,
                $instance->definitionVersion
            );

            // 3. Determine the current step
            $currentStepId = $instance->currentStepId ?? $definition->initialStepId;
            $step = $definition->steps[$currentStepId] ?? null;
            if ($step === null) {
                $this->logger->error('Step not found in workflow', [
                    'step_id' => $currentStepId,
                    'instance_id' => (string)$instanceId,
                    'workflow_id' => $instance->definitionId
                ]);
                throw new \Flowy\Exception\DefinitionNotFoundException(
                    'Step not found: ' . $currentStepId
                );
            }

            // Set stepStartedAt if entering a new step
            if ($instance->stepStartedAt === null || $instance->currentStepId !== $currentStepId) {
                $instance->stepStartedAt = new \DateTimeImmutable();
            }

            // Step-level timeout check
            if ($step->timeoutDuration !== null && $instance->stepStartedAt !== null) {
                $timeoutInterval = \DateInterval::createFromDateString($step->timeoutDuration);
                if ($timeoutInterval !== false) {
                    $timeoutDeadline = $instance->stepStartedAt->add($timeoutInterval);
                    if ((new \DateTimeImmutable()) > $timeoutDeadline) {
                        $instance->status = \Flowy\Model\WorkflowStatus::FAILED;
                        $instance->errorDetails = 'Step timed out after ' . $step->timeoutDuration;
                        $this->logger->error('Step timed out', [
                            'instance_id' => (string)$instance->id,
                            'workflow_id' => $instance->definitionId,
                            'step_id' => $step->id,
                            'timeoutDuration' => $step->timeoutDuration,
                        ]);
                        $this->persistence->save($instance);
                        $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowFailedEvent($instance, null));
                        return;
                    }
                }
            }

            // 4. If instance is PENDING, set to RUNNING and dispatch WorkflowStartedEvent
            if ($instance->status === \Flowy\Model\WorkflowStatus::PENDING) {
                $instance->status = \Flowy\Model\WorkflowStatus::RUNNING;
                $instance->currentStepId = $currentStepId;
                $this->logger->info('Workflow execution started', [
                    'instance_id' => (string)$instance->id,
                    'workflow_id' => $instance->definitionId
                ]);
                $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowStartedEvent($instance));
            }

            // 5. Step event: BeforeStepEnteredEvent
            $this->eventDispatcher->dispatch(new \Flowy\Event\BeforeStepEnteredEvent($instance, $step));

            // 6. Execute all actions for the step
            foreach ($step->actions as $actionDef) {
                try {
                    $this->eventDispatcher->dispatch(new \Flowy\Event\BeforeActionExecutedEvent($instance, $step, $actionDef));
                    $action = $this->actionResolver->resolve($actionDef);
                    $action($instance->context);
                    $this->logger->info('Action executed', [
                        'instance_id' => (string)$instance->id,
                        'workflow_id' => $instance->definitionId,
                        'step_id' => $step->id,
                        'action' => $actionDef->name ?? null
                    ]);
                    $this->eventDispatcher->dispatch(new \Flowy\Event\AfterActionExecutedEvent($instance, $step, $actionDef));
                } catch (\Throwable $e) {
                    $retryPolicy = $step->retryPolicy;
                    $maxAttempts = $retryPolicy?->attempts ?? 0;
                    $delay = $retryPolicy?->fixedDelaySeconds ?? 0;
                    $instance->retryAttempts = ($instance->retryAttempts ?? 0) + 1;
                    if ($maxAttempts > 0 && $instance->retryAttempts <= $maxAttempts) {
                        $instance->status = \Flowy\Model\WorkflowStatus::PENDING;
                        $instance->scheduledAt = (new \DateTimeImmutable())->modify("+{$delay} seconds");
                        $instance->errorDetails = $e->getMessage();
                        $this->eventDispatcher->dispatch(new \Flowy\Event\ActionFailedEvent($instance, $step, $actionDef, $e));
                        $this->persistence->save($instance);
                        $this->logger->warning('Action failed, retry scheduled', [
                            'exception' => $e,
                            'instance_id' => (string)$instance->id,
                            'workflow_id' => $instance->definitionId,
                            'step_id' => $step->id,
                            'action' => $actionDef->name ?? null,
                            'retry_attempts' => $instance->retryAttempts,
                            'max_attempts' => $maxAttempts,
                            'scheduled_at' => $instance->scheduledAt?->format(DATE_ATOM),
                        ]);
                        // Exit early, do not throw, let polling pick up later
                        return;
                    } else {
                        $instance->status = \Flowy\Model\WorkflowStatus::FAILED;
                        $instance->errorDetails = $e->getMessage();
                        $this->eventDispatcher->dispatch(new \Flowy\Event\ActionFailedEvent($instance, $step, $actionDef, $e));
                        $this->persistence->save($instance);
                        $this->logger->error('Action execution failed', [
                            'exception' => $e,
                            'instance_id' => (string)$instance->id,
                            'workflow_id' => $instance->definitionId,
                            'step_id' => $step->id,
                            'action' => $actionDef->name ?? null,
                            'retry_attempts' => $instance->retryAttempts,
                            'max_attempts' => $maxAttempts,
                        ]);
                        throw $e;
                    }
                }
            }

            // 7. Step event: AfterStepExitedEvent
            $this->eventDispatcher->dispatch(new \Flowy\Event\AfterStepExitedEvent($instance, $step));

            // 8. Evaluate transitions (event-based transitions first)
            $transitionTaken = false;
            $signals = $instance->signals ?? [];
            foreach ($step->transitions as $transition) {
                // Event-based transition: only take if matching signal is present
                if ($transition->event !== null) {
                    $signalIndex = null;
                    foreach ($signals as $idx => $signal) {
                        if ($signal['name'] === $transition->event) {
                            $signalIndex = $idx;
                            break;
                        }
                    }
                    if ($signalIndex === null) {
                        continue; // No matching signal, skip this transition
                    }
                    // Remove the signal after use
                    array_splice($instance->signals, $signalIndex, 1);
                } else {
                    // Condition-based or unconditional transition
                    $condition = $this->conditionResolver->resolve($transition);
                    if ($condition !== null && !$condition->evaluate($instance->context)) {
                        continue;
                    }
                }
                $nextStepId = $transition->targetStepId;
                $nextStep = $definition->steps[$nextStepId] ?? null;
                if ($nextStep === null) {
                    $this->logger->error('Next step not found during transition', [
                        'next_step_id' => $nextStepId,
                        'instance_id' => (string)$instance->id,
                        'workflow_id' => $instance->definitionId
                    ]);
                    throw new \Flowy\Exception\DefinitionNotFoundException('Next step not found: ' . $nextStepId);
                }
                $instance->currentStepId = $nextStepId;
                $this->logger->info('Transition taken', [
                    'instance_id' => (string)$instance->id,
                    'workflow_id' => $instance->definitionId,
                    'from_step' => $step->id,
                    'to_step' => $nextStepId
                ]);
                $this->eventDispatcher->dispatch(new \Flowy\Event\TransitionTakenEvent($instance, $step, $nextStep, $transition));
                $transitionTaken = true;
                break;
            }

            // 9. If no transition taken, mark as completed
            if (!$transitionTaken) {
                $instance->status = \Flowy\Model\WorkflowStatus::COMPLETED;
                $this->logger->info('Workflow instance completed', [
                    'instance_id' => (string)$instance->id,
                    'workflow_id' => $instance->definitionId
                ]);
                $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowCompletedEvent($instance));
            }

            // 10. Persist changes
            $this->persistence->save($instance);
        } catch (\Throwable $e) {
            $this->logger->error('Workflow execution failed', [
                'exception' => $e,
                'instance_id' => isset($instance) ? (string)$instance->id : null
            ]);
            if (isset($instance)) {
                $instance->status = \Flowy\Model\WorkflowStatus::FAILED;
                $instance->errorDetails = $e->getMessage();
                $this->persistence->save($instance);
                $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowFailedEvent($instance, $e));
            }
            throw $e;
        }
    }
}
