<?php

declare(strict_types=1);

namespace Flowy\Context;

use Flowy\Exception\InvalidContextKeyException;

/**
 * A key-value store for runtime data associated with a WorkflowInstance.
 * Keys are strings, and values can be of any serializable type.
 */
class WorkflowContext implements \JsonSerializable, \ArrayAccess, \IteratorAggregate, \Countable
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param array<string, mixed> $initialData Initial data for the context.
     */
    public function __construct(array $initialData = [])
    {
        foreach ($initialData as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Retrieves a value from the context by its key.
     *
     * @param string $key The key of the item to retrieve.
     * @param mixed|null $default The default value to return if the key is not found.
     * @return mixed The value associated with the key, or the default value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Sets a value in the context.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to store. Must be serializable.
     * @throws InvalidContextKeyException If the key is invalid.
     */
    public function set(string $key, mixed $value): void
    {
        $this->validateKey($key);
        // Consider adding a check here to ensure $value is serializable if strictness is desired.
        // For MVP, we assume users will store serializable data.
        $this->data[$key] = $value;
    }

    /**
     * Checks if a key exists in the context.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, false otherwise.
     * @throws InvalidContextKeyException If the key is invalid.
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        return array_key_exists($key, $this->data);
    }

    /**
     * Removes a key and its associated value from the context.
     *
     * @param string $key The key to remove.
     * @throws InvalidContextKeyException If the key is invalid.
     */
    public function remove(string $key): void
    {
        $this->validateKey($key);
        unset($this->data[$key]);
    }

    /**
     * Retrieves all data from the context as an associative array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Merges data from another WorkflowContext or an array into this context.
     * Existing keys will be overwritten by the new data.
     *
     * @param WorkflowContext|array<string, mixed> $dataToMerge
     */
    public function merge(WorkflowContext|array $dataToMerge): void
    {
        $data = $dataToMerge instanceof WorkflowContext ? $dataToMerge->all() : $dataToMerge;
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Validates the context key.
     *
     * @param mixed $key
     * @throws InvalidContextKeyException
     */
    private function validateKey(mixed $key): void
    {
        if (!is_string($key) || trim($key) === '') {
            throw new InvalidContextKeyException('Context key must be a non-empty string.');
        }
    }

    /**
     * Specify data which should be serialized to JSON.
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * Create a WorkflowContext from a JSON string.
     *
     * @param string $jsonString
     * @return static
     * @throws \JsonException
     */
    public static function fromJson(string $jsonString): static
    {
        $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        return new static($data);
    }

    // ArrayAccess methods

    /** @param string $offset */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /** @param string $offset */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /** @param string $offset */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    // IteratorAggregate method
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    // Countable method
    public function count(): int
    {
        return count($this->data);
    }
}
