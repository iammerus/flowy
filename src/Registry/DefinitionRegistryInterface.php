<?php

declare(strict_types=1);

namespace Flowy\Registry;

use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Exception\DefinitionAlreadyExistsException;
use Flowy\Exception\DefinitionNotFoundException;
use Flowy\Exception\DefinitionLoadingException;

/**
 * Interface for a registry that stores and retrieves WorkflowDefinition objects.
 */
interface DefinitionRegistryInterface
{
    /**
     * Adds a workflow definition to the registry.
     *
     * @param WorkflowDefinition $definition The workflow definition to add.
     * @throws DefinitionAlreadyExistsException If a definition with the same ID and version already exists.
     */
    public function addDefinition(WorkflowDefinition $definition): void;

    /**
     * Retrieves a specific workflow definition by its ID and optionally version.
     *
     * If version is null, the latest version of the workflow definition should be returned.
     * Implementations should define what "latest" means (e.g., highest semantic version or most recently added).
     *
     * @param string $workflowId The ID of the workflow definition.
     * @param string|null $version The specific version of the workflow definition. Null for latest.
     * @return WorkflowDefinition
     * @throws DefinitionNotFoundException If the definition is not found.
     */
    public function getDefinition(string $workflowId, ?string $version = null): WorkflowDefinition;

    /**
     * Checks if a specific workflow definition exists in the registry.
     *
     * @param string $workflowId The ID of the workflow definition.
     * @param string|null $version The specific version. If null, checks if any version for the ID exists.
     * @return bool True if the definition exists, false otherwise.
     */
    public function hasDefinition(string $workflowId, ?string $version = null): bool;

    /**
     * Finds workflow definitions.
     *
     * - If $workflowId is provided, returns all versions for that workflow ID.
     * - If $workflowId is null, returns all workflow definitions in the registry.
     *
     * @param string|null $workflowId Optional workflow ID to filter by.
     * @return WorkflowDefinition[] An array of workflow definitions, empty if none found.
     */
    public function findDefinitions(?string $workflowId = null): array;

    /**
     * Loads a workflow definition from a source (e.g., class name for attribute-based definitions)
     * using an appropriate loader, adds it to the registry, and returns the loaded definition.
     *
     * For attribute-based definitions, the source identifier is the fully qualified class name.
     *
     * @param string $sourceIdentifier The identifier of the source (e.g., FQCN).
     * @param string $sourceType The type of the source (e.g., 'class' for attribute loader). Defaults to 'class'.
     * @return WorkflowDefinition The loaded and added workflow definition.
     * @throws DefinitionLoadingException If loading from the source fails.
     * @throws DefinitionAlreadyExistsException If the loaded definition (ID and version) already exists in the registry.
     * @throws \InvalidArgumentException If the sourceType is unsupported.
     */
    public function addDefinitionFromSource(string $sourceIdentifier, string $sourceType = 'class'): WorkflowDefinition;
}
