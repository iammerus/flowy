<?php

declare(strict_types=1);

namespace Flowy\Loader;

use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\TransitionDefinition;
use Flowy\Model\Data\RetryPolicy; // Though StepAttribute handles its creation
use Flowy\Attribute\Workflow as WorkflowAttribute;
use Flowy\Attribute\Step as StepAttribute;
use Flowy\Attribute\Action as ActionAttribute;
use Flowy\Attribute\Transition as TransitionAttribute;
use Flowy\Exception\DefinitionLoadingException;
use ReflectionClass;
use ReflectionMethod;

class AttributeDefinitionLoader
{
    /**
     * Loads a WorkflowDefinition from a PHP class annotated with Flowy attributes.
     *
     * @param string $className The fully qualified class name of the workflow definition.
     * @return WorkflowDefinition
     * @throws DefinitionLoadingException If the class or its attributes are invalid or missing.
     * @throws \ReflectionException
     */
    public function loadFromClass(string $className): WorkflowDefinition
    {
        if (!class_exists($className)) {
            throw new DefinitionLoadingException("Workflow definition class '{$className}' not found.");
        }

        $classReflection = new ReflectionClass($className);
        $workflowAttributeInstance = $this->getWorkflowAttribute($classReflection, $className);

        $stepDefinitionsMap = [];
        $initialStepId = null;
        $foundInitialStep = false;

        foreach ($classReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $methodReflection) {
            $methodStepAttributes = $methodReflection->getAttributes(StepAttribute::class);
            if (empty($methodStepAttributes)) {
                continue; // Skip methods not marked as steps
            }

            // Actions and Transitions are defined per method and apply to all steps on that method
            $actionDefinitions = $this->loadActionDefinitionsForMethod($methodReflection);
            $transitionDefinitions = $this->loadTransitionDefinitionsForMethod($methodReflection);

            foreach ($methodStepAttributes as $stepAttributeRef) {
                /** @var StepAttribute $stepAttr */
                $stepAttr = $stepAttributeRef->newInstance();
                $currentStepId = $stepAttr->id;

                if (isset($stepDefinitionsMap[$currentStepId])) {
                    throw new DefinitionLoadingException(
                        "Duplicate step ID '{$currentStepId}' found in workflow '{$workflowAttributeInstance->id}'. Step IDs must be unique."
                    );
                }

                if ($stepAttr->initial) {
                    if ($foundInitialStep) {
                        throw new DefinitionLoadingException(
                            "Multiple initial steps defined in workflow '{$workflowAttributeInstance->id}'. Step '{$currentStepId}' and '{$initialStepId}' are both marked as initial."
                        );
                    }
                    $initialStepId = $currentStepId;
                    $foundInitialStep = true;
                }

                $stepDefinition = new StepDefinition(
                    id: $currentStepId,
                    actions: $actionDefinitions,
                    transitions: $transitionDefinitions,
                    name: $methodReflection->getName(), // Convention: method name as step name
                    description: null, // PHPDoc parsing for description is out of MVP scope
                    isInitial: $stepAttr->initial,
                    type: 'action', // Default for MVP
                    retryPolicy: $stepAttr->getRetryPolicyObject(),
                    timeoutDuration: $stepAttr->timeout
                );
                $stepDefinitionsMap[$currentStepId] = $stepDefinition;
            }
        }

        if (empty($stepDefinitionsMap)) {
            throw new DefinitionLoadingException(
                "Workflow definition '{$workflowAttributeInstance->id}' version '{$workflowAttributeInstance->version}' must contain at least one step."
            );
        }

        if (!$foundInitialStep) {
            throw new DefinitionLoadingException(
                "Workflow definition '{$workflowAttributeInstance->id}' version '{$workflowAttributeInstance->version}' must have exactly one initial step defined."
            );
        }

        return new WorkflowDefinition(
            id: $workflowAttributeInstance->id,
            version: $workflowAttributeInstance->version,
            name: $workflowAttributeInstance->name,
            initialStepId: $initialStepId,
            steps: array_values($stepDefinitionsMap),
            initialContextSchema: $workflowAttributeInstance->initialContextSchema
        );
    }

    /**
     * @throws DefinitionLoadingException
     * @throws \ReflectionException
     */
    private function getWorkflowAttribute(ReflectionClass $classReflection, string $className): WorkflowAttribute
    {
        $workflowAttributes = $classReflection->getAttributes(WorkflowAttribute::class);
        if (empty($workflowAttributes)) {
            throw new DefinitionLoadingException(
                "The class '{$className}' must have exactly one Flowy\\Attribute\\Workflow attribute. None found."
            );
        }
        if (count($workflowAttributes) > 1) {
            throw new DefinitionLoadingException(
                "The class '{$className}' must have exactly one Flowy\\Attribute\\Workflow attribute. Multiple found."
            );
        }
        return $workflowAttributes[0]->newInstance();
    }

    /**
     * @return ActionDefinition[]
     * @throws \ReflectionException
     */
    private function loadActionDefinitionsForMethod(ReflectionMethod $methodReflection): array
    {
        $actionDefinitions = [];
        $actionAttributeRefs = $methodReflection->getAttributes(ActionAttribute::class);
        foreach ($actionAttributeRefs as $actionAttributeRef) {
            /** @var ActionAttribute $actionAttr */
            $actionAttr = $actionAttributeRef->newInstance();
            $actionDefinitions[] = new ActionDefinition(
                identifier: $actionAttr->getActionIdentifier(),
                parameters: $actionAttr->parameters,
                description: $actionAttr->description
                // Type is inferred by ActionDefinition constructor
            );
        }
        return $actionDefinitions;
    }

    /**
     * @return TransitionDefinition[]
     * @throws \ReflectionException
     */
    private function loadTransitionDefinitionsForMethod(ReflectionMethod $methodReflection): array
    {
        $transitionDefinitions = [];
        $transitionAttributeRefs = $methodReflection->getAttributes(TransitionAttribute::class);
        foreach ($transitionAttributeRefs as $transitionAttributeRef) {
            /** @var TransitionAttribute $transitionAttr */
            $transitionAttr = $transitionAttributeRef->newInstance();
            $transitionDefinitions[] = new TransitionDefinition(
                targetStepId: $transitionAttr->to,
                conditionIdentifier: $transitionAttr->condition,
                // conditionType is inferred by TransitionDefinition constructor
                event: $transitionAttr->event
            );
        }
        return $transitionDefinitions;
    }
}