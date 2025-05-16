<?php

declare(strict_types=1);

namespace Flowy\Model\Data;

/**
 * Defines the retry policy for a step.
 *
 * Supports:
 * - attempts: Maximum number of retry attempts.
 * - fixedDelaySeconds: Fixed delay between retries (seconds).
 * - exponentialBackoffSeconds: Initial delay for exponential backoff (seconds).
 * - jitterFactor: Jitter factor (0.0-1.0) for randomizing delay.
 * - maxDelaySeconds: Maximum delay between retries (seconds).
 */
class RetryPolicy
{
    /**
     * @param int $attempts Maximum number of retry attempts (>=0).
     * @param int $fixedDelaySeconds Fixed delay in seconds before retrying (>=0).
     * @param int $exponentialBackoffSeconds Initial delay for exponential backoff (>=0).
     * @param float $jitterFactor Jitter factor (0.0-1.0).
     * @param int $maxDelaySeconds Maximum delay in seconds between retries (>=0).
     */
    public function __construct(
        public readonly int $attempts = 0,
        public readonly int $fixedDelaySeconds = 0,
        public readonly int $exponentialBackoffSeconds = 0,
        public readonly float $jitterFactor = 0.0,
        public readonly int $maxDelaySeconds = 0
    ) {
        if ($this->attempts < 0) {
            throw new \InvalidArgumentException('Attempts must be a non-negative integer.');
        }
        if ($this->fixedDelaySeconds < 0) {
            throw new \InvalidArgumentException('Fixed delay seconds must be a non-negative integer.');
        }
        if ($this->exponentialBackoffSeconds < 0) {
            throw new \InvalidArgumentException('Exponential backoff seconds must be a non-negative integer.');
        }
        if ($this->jitterFactor < 0.0 || $this->jitterFactor > 1.0) {
            throw new \InvalidArgumentException('Jitter factor must be between 0.0 and 1.0.');
        }
        if ($this->maxDelaySeconds < 0) {
            throw new \InvalidArgumentException('Max delay seconds must be a non-negative integer.');
        }
    }

    /**
     * Calculate the delay (in seconds) before the next retry attempt.
     *
     * @param int $attemptNumber The current attempt number (1-based).
     * @return int Delay in seconds.
     */
    public function getDelayForAttempt(int $attemptNumber): int
    {
        if ($attemptNumber < 1) {
            throw new \InvalidArgumentException('Attempt number must be >= 1.');
        }
        // Exponential backoff logic takes precedence if set
        $delay = $this->fixedDelaySeconds;
        if ($this->exponentialBackoffSeconds > 0) {
            $delay = $this->exponentialBackoffSeconds * (2 ** ($attemptNumber - 1));
        }
        // Apply max delay if set
        if ($this->maxDelaySeconds > 0 && $delay > $this->maxDelaySeconds) {
            $delay = $this->maxDelaySeconds;
        }
        // Apply jitter if set
        if ($this->jitterFactor > 0.0) {
            $jitter = (int) round($delay * $this->jitterFactor * (mt_rand() / mt_getrandmax()));
            $delay += $jitter;
        }
        return $delay;
    }
}
