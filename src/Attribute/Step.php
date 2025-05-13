<?php

declare(strict_types=1);

namespace Flowy\Attribute;

use Attribute;
use Flowy\Model\Data\RetryPolicy; // Will be created if not already

/**
 * Attribute to define a method as a Step within a Workflow Definition class.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Step
{
    /**
     * @param string $id Unique identifier for the step within the workflow definition.
     * @param bool $initial Whether this is the starting step of the workflow.
     * @param array<string, mixed> $retryPolicy An array defining the retry policy, e.g., ["attempts" => 3, "fixedDelaySeconds" => 10].
     *        This will be mapped to a RetryPolicy object.
     * @param string|null $timeout Optional duration string for step execution (e.g., "PT5M"). Not fully implemented in MVP executor.
     */
    public function __construct(
        public readonly string $id,
        public readonly bool $initial = false,
        public readonly ?array $retryPolicy = null, // Array to be mapped to RetryPolicy object
        public readonly ?string $timeout = null
    ) {}

    public function getRetryPolicyObject(): ?RetryPolicy
    {
        if ($this->retryPolicy === null) {
            return null;
        }
        return new RetryPolicy(
            attempts: $this->retryPolicy['attempts'] ?? 0,
            fixedDelaySeconds: $this->retryPolicy['fixedDelaySeconds'] ?? 0
        );
    }
}
