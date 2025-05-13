<?php

declare(strict_types=1);

namespace Flowy\Model;

/**
 * Enum representing the possible statuses of a WorkflowInstance.
 */
enum WorkflowStatus: string
{
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case PAUSED = 'PAUSED';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    // Future statuses like TIMED_OUT could be added here.

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED => true,
            default => false,
        };
    }

    public function canTransitionTo(WorkflowStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::RUNNING, self::CANCELLED, self::FAILED]),
            self::RUNNING => in_array($newStatus, [self::PAUSED, self::COMPLETED, self::FAILED, self::CANCELLED]),
            self::PAUSED => in_array($newStatus, [self::RUNNING, self::CANCELLED, self::FAILED]),
            self::COMPLETED => false, // Terminal state
            self::FAILED => in_array($newStatus, [self::PENDING]), // For retry logic
            self::CANCELLED => false, // Terminal state
        };
    }
}
