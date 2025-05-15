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
final class WorkflowExecutor
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
                throw new \Flowy\Exception\FlowyExceptionInterface(
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
                throw new \Flowy\Exception\FlowyExceptionInterface(
                    'Step not found: ' . $currentStepId
                );
            }

            // 4. If instance is PENDING, set to RUNNING and dispatch WorkflowStartedEvent
            if ($instance->status === \Flowy\Model\WorkflowStatus::PENDING) {
                $instance->status = \Flowy\Model\WorkflowStatus::RUNNING;
                $instance->currentStepId = $currentStepId;
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
                    $this->eventDispatcher->dispatch(new \Flowy\Event\AfterActionExecutedEvent($instance, $step, $actionDef));
                } catch (\Throwable $e) {
                    $instance->status = \Flowy\Model\WorkflowStatus::FAILED;
                    $instance->errorDetails = $e->getMessage();
                    $this->eventDispatcher->dispatch(new \Flowy\Event\ActionFailedEvent($instance, $step, $actionDef, $e));
                    $this->persistence->save($instance);
                    $this->logger->error('Action execution failed', ['exception' => $e]);
                    throw $e;
                }
            }

            // 7. Step event: AfterStepExitedEvent
            $this->eventDispatcher->dispatch(new \Flowy\Event\AfterStepExitedEvent($instance, $step));

            // 8. Evaluate transitions
            $transitionTaken = false;
            foreach ($step->transitions as $transition) {
                $condition = $this->conditionResolver->resolve($transition);
                if ($condition === null || $condition->evaluate($instance->context)) {
                    $nextStepId = $transition->targetStepId;
                    $nextStep = $definition->steps[$nextStepId] ?? null;
                    if ($nextStep === null) {
                        throw new \Flowy\Exception\FlowyExceptionInterface('Next step not found: ' . $nextStepId);
                    }
                    $instance->currentStepId = $nextStepId;
                    $this->eventDispatcher->dispatch(new \Flowy\Event\TransitionTakenEvent($instance, $step, $nextStep, $transition));
                    $transitionTaken = true;
                    break;
                }
            }

            // 9. If no transition taken, mark as completed
            if (!$transitionTaken) {
                $instance->status = \Flowy\Model\WorkflowStatus::COMPLETED;
                $this->eventDispatcher->dispatch(new \Flowy\Event\WorkflowCompletedEvent($instance));
            }

            // 10. Persist changes
            $this->persistence->save($instance);
        } catch (\Throwable $e) {
            $this->logger->error('Workflow execution failed', ['exception' => $e]);
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
