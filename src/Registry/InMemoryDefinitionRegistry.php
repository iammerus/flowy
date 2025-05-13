<?php

declare(strict_types=1);

namespace Flowy\Registry;

use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Exception\DefinitionAlreadyExistsException;
use Flowy\Exception\DefinitionNotFoundException;
use Flowy\Exception\DefinitionLoadingException;
use Flowy\Loader\AttributeDefinitionLoader;
use Flowy\Loader\YamlDefinitionLoader;
use InvalidArgumentException;

/**
 * An in-memory implementation of the DefinitionRegistryInterface.
 * Stores workflow definitions in an array.
 */
class InMemoryDefinitionRegistry implements DefinitionRegistryInterface
{
    /**
     * @var array<string, array<string, WorkflowDefinition>> Stores definitions keyed by ID, then by version.
     * e.g., $definitions['order_workflow']['1.0.0'] = WorkflowDefinitionObject
     */
    private array $definitions = [];

    /**
     * @var array<string, string> Stores the latest version string for each workflow ID.
     * e.g., $latestVersions['order_workflow'] = '1.0.1'
     */
    private array $latestVersions = [];

    private AttributeDefinitionLoader $attributeLoader;
    private YamlDefinitionLoader $yamlLoader;

    public function __construct(
        ?AttributeDefinitionLoader $attributeLoader = null,
        ?YamlDefinitionLoader $yamlLoader = null
    ) {
        $this->attributeLoader = $attributeLoader ?? new AttributeDefinitionLoader();
        $this->yamlLoader = $yamlLoader ?? new YamlDefinitionLoader();
    }

    public function addDefinition(WorkflowDefinition $definition): void
    {
        $id = $definition->id;
        $version = $definition->version;

        if ($this->hasDefinition($id, $version)) {
            throw new DefinitionAlreadyExistsException(
                "Workflow definition with ID '{$id}' and version '{$version}' already exists."
            );
        }

        $this->definitions[$id][$version] = $definition;

        // Update latest version tracker
        if (!isset($this->latestVersions[$id]) || version_compare($version, $this->latestVersions[$id], '>')) {
            $this->latestVersions[$id] = $version;
        }
    }

    public function getDefinition(string $workflowId, ?string $version = null): WorkflowDefinition
    {
        if (!isset($this->definitions[$workflowId])) {
            throw new DefinitionNotFoundException("No workflow definitions found for ID '{$workflowId}'.");
        }

        if ($version === null) {
            // Get latest version
            if (!isset($this->latestVersions[$workflowId])) {
                // This case should ideally not happen if definitions[$workflowId] is set and addDefinition is used.
                throw new DefinitionNotFoundException("No versions found for workflow ID '{$workflowId}'.");
            }
            $latestVersion = $this->latestVersions[$workflowId];
            if (!isset($this->definitions[$workflowId][$latestVersion])) {
                 // This indicates an inconsistency, should not happen with proper addDefinition logic
                throw new DefinitionNotFoundException("Latest version '{$latestVersion}' for workflow ID '{$workflowId}' not found in store.");
            }
            return $this->definitions[$workflowId][$latestVersion];
        }

        // Get specific version
        if (!isset($this->definitions[$workflowId][$version])) {
            throw new DefinitionNotFoundException(
                "Workflow definition with ID '{$workflowId}' and version '{$version}' not found."
            );
        }
        return $this->definitions[$workflowId][$version];
    }

    public function hasDefinition(string $workflowId, ?string $version = null): bool
    {
        if (!isset($this->definitions[$workflowId])) {
            return false;
        }
        if ($version === null) {
            return !empty($this->definitions[$workflowId]);
        }
        return isset($this->definitions[$workflowId][$version]);
    }

    public function findDefinitions(?string $workflowId = null): array
    {
        if ($workflowId !== null) {
            if (!isset($this->definitions[$workflowId])) {
                return [];
            }
            return array_values($this->definitions[$workflowId]);
        }

        // Return all definitions from all workflow IDs
        $allDefinitions = [];
        foreach ($this->definitions as $versions) {
            foreach ($versions as $definition) {
                $allDefinitions[] = $definition;
            }
        }
        return $allDefinitions;
    }

    /**
     * {@inheritDoc}
     */
    public function addDefinitionFromSource(string $sourceIdentifier, string $sourceType = 'class'): WorkflowDefinition
    {
        // If source type is not explicitly provided, try to detect it
        if ($sourceType === 'class' && $this->isYamlFilePath($sourceIdentifier)) {
            $sourceType = 'yaml';
        }

        $definition = match ($sourceType) {
            'class' => $this->loadFromClass($sourceIdentifier),
            'yaml' => $this->loadFromYaml($sourceIdentifier),
            default => throw new InvalidArgumentException(
                "Unsupported source type '{$sourceType}'. Supported types are: 'class', 'yaml'."
            )
        };

        $this->addDefinition($definition); // This can throw DefinitionAlreadyExistsException

        return $definition;
    }

    /**
     * Load a workflow definition from a PHP class.
     *
     * @param string $className The fully qualified class name.
     * @return WorkflowDefinition
     * @throws DefinitionLoadingException If loading fails.
     */
    private function loadFromClass(string $className): WorkflowDefinition
    {
        try {
            return $this->attributeLoader->loadFromClass($className);
        } catch (\ReflectionException $e) {
            throw new DefinitionLoadingException(
                "Failed to load workflow definition from class '{$className}' due to a reflection error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Load a workflow definition from a YAML file.
     *
     * @param string $filePath The path to the YAML file.
     * @return WorkflowDefinition
     * @throws DefinitionLoadingException If loading fails.
     */
    private function loadFromYaml(string $filePath): WorkflowDefinition
    {
        return $this->yamlLoader->loadFromFile($filePath);
    }

    /**
     * Check if the given path is likely a YAML file.
     *
     * @param string $path The path to check.
     * @return bool True if the path appears to be a YAML file.
     */
    private function isYamlFilePath(string $path): bool
    {
        // Check if path ends with .yml or .yaml (case insensitive)
        return (bool) preg_match('/\.(ya?ml)$/i', $path);
    }
}
