<?php

declare(strict_types=1);

namespace Flowy\Model\Data;

/**
 * Defines the retry policy for a step.
 * As per SPEC.md (MVP): supports attempts and fixedDelaySeconds.
 */
class RetryPolicy
{
    /**
     * @param int $attempts Maximum number of retry attempts (e.g., 3).
     * @param int $fixedDelaySeconds Delay in seconds before retrying (e.g., 10).
     */
    public function __construct(
        public readonly int $attempts = 0,
        public readonly int $fixedDelaySeconds = 0
    ) {
        if ($this->attempts < 0) {
            throw new \InvalidArgumentException('Attempts must be a non-negative integer.');
        }
        if ($this->fixedDelaySeconds < 0) {
            throw new \InvalidArgumentException('Fixed delay seconds must be a non-negative integer.');
        }
    }
}
