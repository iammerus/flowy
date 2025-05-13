<?php

declare(strict_types=1);

namespace Flowy\Loader;

use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\TransitionDefinition;
use Flowy\Model\Data\RetryPolicy;
use Flowy\Exception\DefinitionLoadingException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Loads a WorkflowDefinition from a YAML file.
 */
class YamlDefinitionLoader
{
    private const REQUIRED_WORKFLOW_KEYS = ['id', 'version', 'steps'];
    private const REQUIRED_STEP_KEYS = ['id'];

    /**
     * Loads a WorkflowDefinition from a YAML file.
     *
     * @param string $filePath The absolute path to the YAML file.
     * @return WorkflowDefinition
     * @throws DefinitionLoadingException If the file cannot be read, parsed, or is invalid.
     */
    public function loadFromFile(string $filePath): WorkflowDefinition
    {
        if (!is_readable($filePath) || !is_file($filePath)) {
            throw new DefinitionLoadingException("Workflow definition YAML file '{$filePath}' is not readable or does not exist.");
        }

        try {
            $yamlContent = Yaml::parseFile($filePath);
        } catch (ParseException $e) {
            throw new DefinitionLoadingException("Failed to parse YAML file '{$filePath}': " . $e->getMessage(), 0, $e);
        }

        if (!is_array($yamlContent)) {
            throw new DefinitionLoadingException("Invalid YAML structure in '{$filePath}'. Expected a YAML object/mapping.");
        }

        // Validate required workflow keys
        foreach (self::REQUIRED_WORKFLOW_KEYS as $requiredKey) {
            if (!isset($yamlContent[$requiredKey])) {
                throw new DefinitionLoadingException("Missing required key '{$requiredKey}' in workflow definition.");
            }
        }

        // Parse steps and build step definitions
        $steps = [];
        $initialStepId = $yamlContent['initialStepId'] ?? null;
        $foundInitialStep = false;

        foreach ($yamlContent['steps'] as $stepData) {
            if (!is_array($stepData)) {
                throw new DefinitionLoadingException("Invalid step definition structure. Expected a YAML object/mapping.");
            }

            // Validate required step keys
            foreach (self::REQUIRED_STEP_KEYS as $requiredKey) {
                if (!isset($stepData[$requiredKey])) {
                    throw new DefinitionLoadingException("Missing required key '{$requiredKey}' in step definition.");
                }
            }

            $stepId = $stepData['id'];
            $isInitial = false;

            // Check if this step is the initial step
            if ($initialStepId !== null && $stepId === $initialStepId) {
                $isInitial = true;
                $foundInitialStep = true;
            }

            // Parse actions
            $actions = [];
            if (isset($stepData['actions']) && is_array($stepData['actions'])) {
                foreach ($stepData['actions'] as $actionData) {
                    $actions[] = $this->parseAction($actionData);
                }
            }

            // Parse transitions
            $transitions = [];
            if (isset($stepData['transitions']) && is_array($stepData['transitions'])) {
                foreach ($stepData['transitions'] as $transitionData) {
                    $transitions[] = $this->parseTransition($transitionData);
                }
            }

            // Parse retry policy
            $retryPolicy = null;
            if (isset($stepData['retryPolicy']) && is_array($stepData['retryPolicy'])) {
                $retryData = $stepData['retryPolicy'];
                $retryPolicy = new RetryPolicy(
                    attempts: $retryData['attempts'] ?? 0,
                    fixedDelaySeconds: $retryData['fixedDelaySeconds'] ?? 0
                );
            }

            // Create the StepDefinition
            $steps[] = new StepDefinition(
                id: $stepId,
                actions: $actions,
                transitions: $transitions,
                name: $stepData['name'] ?? null,
                description: $stepData['description'] ?? null,
                isInitial: $isInitial,
                type: $stepData['type'] ?? 'action',
                retryPolicy: $retryPolicy,
                timeoutDuration: $stepData['timeout'] ?? null
            );
        }

        if (empty($steps)) {
            throw new DefinitionLoadingException("Workflow definition must contain at least one step.");
        }

        // Check initial step designation
        if ($initialStepId === null) {
            throw new DefinitionLoadingException("Workflow definition must specify an 'initialStepId'.");
        }

        if (!$foundInitialStep) {
            throw new DefinitionLoadingException("The specified initialStepId '{$initialStepId}' does not match any step ID in the workflow.");
        }

        // Create and return the WorkflowDefinition
        return new WorkflowDefinition(
            id: $yamlContent['id'],
            version: $yamlContent['version'],
            initialStepId: $initialStepId,
            steps: $steps,
            name: $yamlContent['name'] ?? null,
            description: $yamlContent['description'] ?? null,
            initialContextSchema: $yamlContent['initialContextSchema'] ?? null
        );
    }

    /**
     * Parse an action definition from YAML data.
     *
     * @param array<string, mixed> $actionData The action data from YAML.
     * @return ActionDefinition
     * @throws DefinitionLoadingException If the action data is invalid.
     */
    private function parseAction(array $actionData): ActionDefinition
    {
        if (!isset($actionData['service']) && !isset($actionData['callable'])) {
            throw new DefinitionLoadingException("Action definition must specify either a 'service' or a 'callable'.");
        }

        $identifier = $actionData['service'] ?? $actionData['callable'] ?? null;
        
        if ($identifier === null) {
            throw new DefinitionLoadingException("Invalid action identifier.");
        }

        return new ActionDefinition(
            identifier: $identifier,
            parameters: $actionData['parameters'] ?? [],
            description: $actionData['description'] ?? null
        );
    }

    /**
     * Parse a transition definition from YAML data.
     *
     * @param array<string, mixed> $transitionData The transition data from YAML.
     * @return TransitionDefinition
     * @throws DefinitionLoadingException If the transition data is invalid.
     */
    private function parseTransition(array $transitionData): TransitionDefinition
    {
        if (!isset($transitionData['target'])) {
            throw new DefinitionLoadingException("Transition definition must specify a 'target' step ID.");
        }

        return new TransitionDefinition(
            targetStepId: $transitionData['target'],
            conditionIdentifier: $transitionData['condition'] ?? null,
            event: $transitionData['event'] ?? null
        );
    }
}
