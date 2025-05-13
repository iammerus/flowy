<?php

declare(strict_types=1);

namespace Flowy\Model\Data;

/**
 * Defines the overall structure and properties of a workflow.
 */
class WorkflowDefinition
{
    /**
     * Unique identifier for the workflow definition (e.g., "order_processing").
     */
    public readonly string $id;

    /**
     * Version of the workflow definition (e.g., "1.0.0", "2.0-beta").
     */
    public readonly string $version;

    /**
     * Optional human-readable name for the workflow.
     */
    public readonly ?string $name;

    /**
     * Optional description of the workflow's purpose.
     */
    public readonly ?string $description;

    /**
     * The identifier of the initial step when a new instance of this workflow is started.
     */
    public readonly string $initialStepId;

    /**
     * Array of step definitions that make up this workflow.
     * The keys of the array are the step IDs.
     *
     * @var array<string, StepDefinition>
     */
    public readonly array $steps;

    /**
     * Optional schema for the initial context required or expected by this workflow.
     * (For future use, not strictly enforced in MVP).
     *
     * @var array<string, mixed>|null
     */
    public readonly ?array $initialContextSchema;

    /**
     * @param string $id Unique identifier for the workflow definition.
     * @param string $version Version of the workflow definition.
     * @param string $initialStepId Identifier of the initial step.
     * @param StepDefinition[] $steps Array of step definitions.
     * @param string|null $name Optional name.
     * @param string|null $description Optional description.
     * @param array<string, mixed>|null $initialContextSchema Optional initial context schema.
     */
    public function __construct(
        string $id,
        string $version,
        string $initialStepId,
        array $steps,
        ?string $name = null,
        ?string $description = null,
        ?array $initialContextSchema = null
    ) {
        $this->id = $id;
        $this->version = $version;
        $this->name = $name;
        $this->description = $description;
        $this->initialStepId = $initialStepId;
        $this->initialContextSchema = $initialContextSchema;

        $stepMap = [];
        $foundInitial = false;
        foreach ($steps as $step) {
            if (! $step instanceof StepDefinition) {
                throw new \InvalidArgumentException(
                    'All steps must be instances of StepDefinition.'
                );
            }
            if (isset($stepMap[$step->id])) {
                throw new \InvalidArgumentException(
                    "Duplicate step ID found: {$step->id}. Step IDs must be unique within a workflow definition."
                );
            }
            $stepMap[$step->id] = $step;
            if ($step->id === $initialStepId) {
                $foundInitial = true;
            }
        }

        if (empty($stepMap)) {
            throw new \InvalidArgumentException('A workflow definition must have at least one step.');
        }

        if (! $foundInitial) {
            throw new \InvalidArgumentException(
                "The specified initialStepId '{$initialStepId}' does not match any of the provided step IDs."
            );
        }

        $this->steps = $stepMap;
    }

    public function getStep(string $stepId): ?StepDefinition
    {
        return $this->steps[$stepId] ?? null;
    }

    public function getInitialStep(): StepDefinition
    {
        // The constructor ensures this step exists.
        return $this->steps[$this->initialStepId];
    }
}
