<?php

declare(strict_types=1);

namespace Flowy\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Flowy\Context\WorkflowContext;

/**
 * Represents a single, live execution of a WorkflowDefinition.
 */
#[ORM\Entity]
#[ORM\Table(name: 'workflow_instances')]
class WorkflowInstance
{
    /**
     * Unique identifier for the workflow instance.
     */
    #[ORM\Id]
    #[ORM\Column(type: 'workflow_instance_id', unique: true)]
    public readonly WorkflowInstanceIdInterface $id;

    /**
     * Identifier of the workflow definition this instance is based on.
     */
    #[ORM\Column(type: 'string')]
    public readonly string $definitionId;

    /**
     * Version of the workflow definition this instance is based on.
     */
    #[ORM\Column(type: 'string')]
    public readonly string $definitionVersion;

    /**
     * Current status of the workflow instance.
     */
    #[ORM\Column(type: 'string', enumType: WorkflowStatus::class)]
    public WorkflowStatus $status;

    /**
     * The context data associated with this workflow instance.
     */
    #[ORM\Column(type: \Flowy\Persistence\Doctrine\Type\WorkflowContextType::NAME)]
    public WorkflowContext $context;

    /**
     * Timestamp of when the instance was created.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    public readonly DateTimeImmutable $createdAt;

    /**
     * Timestamp of the last update to the instance.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $updatedAt;

    /**
     * Optional business key for correlating this instance with external systems.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    public readonly ?string $businessKey;

    /**
     * Identifier of the current step the workflow instance is at.
     * Null if the workflow hasn't started or has completed.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $currentStepId;

    /**
     * Log of events and state changes that occurred during the instance execution.
     * Each entry could be an array like: ['timestamp' => DateTimeImmutable, 'message' => string, 'stepId' => ?string]
     *
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: 'json')]
    public array $history = [];

    /**
     * Details of the last error that occurred, if any.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $errorDetails = null;

    /**
     * Number of retry attempts for the current failing step.
     * Reset when moving to a new step or successful retry.
     */
    #[ORM\Column(type: 'integer')]
    public int $retryAttempts = 0;

    /**
     * Version number for optimistic locking.
     */
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    public int $version = 1;

    /**
     * Identifier of the worker that has locked this instance (for pessimistic locking - Phase 3).
     */
    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $lockedBy = null;

    /**
     * Timestamp when the pessimistic lock expires (Phase 3).
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $lockExpiresAt = null;

    /**
     * Timestamp when this instance is scheduled for its next processing (e.g., for delayed retries or timed transitions - Phase 2+).
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $scheduledAt = null;

    /**
     * Timestamp when the current step was entered (for timeout handling).
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $stepStartedAt = null;

    /**
     * @param WorkflowInstanceIdInterface $id
     * @param string $definitionId
     * @param string $definitionVersion
     * @param WorkflowStatus $status
     * @param WorkflowContext $context
     * @param DateTimeImmutable $createdAt
     * @param DateTimeImmutable $updatedAt
     * @param string|null $businessKey
     * @param string|null $currentStepId
     * @param array<int, array<string, mixed>> $history
     * @param string|null $errorDetails
     * @param int $retryAttempts
     * @param int $version
     * @param string|null $lockedBy
     * @param DateTimeImmutable|null $lockExpiresAt
     * @param DateTimeImmutable|null $scheduledAt
     * @param DateTimeImmutable|null $stepStartedAt
     */
    public function __construct(
        WorkflowInstanceIdInterface $id,
        string $definitionId,
        string $definitionVersion,
        WorkflowStatus $status,
        WorkflowContext $context,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?string $businessKey = null,
        ?string $currentStepId = null,
        array $history = [],
        ?string $errorDetails = null,
        int $retryAttempts = 0,
        int $version = 1,
        ?string $lockedBy = null,
        ?DateTimeImmutable $lockExpiresAt = null,
        ?DateTimeImmutable $scheduledAt = null,
        ?DateTimeImmutable $stepStartedAt = null
    ) {
        $this->id = $id;
        $this->definitionId = $definitionId;
        $this->definitionVersion = $definitionVersion;
        $this->status = $status;
        $this->context = $context;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->businessKey = $businessKey;
        $this->currentStepId = $currentStepId;
        $this->history = $history;
        $this->errorDetails = $errorDetails;
        $this->retryAttempts = $retryAttempts;
        $this->version = $version;
        $this->lockedBy = $lockedBy;
        $this->lockExpiresAt = $lockExpiresAt;
        $this->scheduledAt = $scheduledAt;
        $this->stepStartedAt = $stepStartedAt;
    }

    public function addHistoryEvent(string $message, ?string $stepId = null): void
    {
        $this->history[] = [
            'timestamp' => new DateTimeImmutable(),
            'message'   => $message,
            'stepId'    => $stepId ?? $this->currentStepId,
        ];
        $this->updatedAt = new DateTimeImmutable();
    }

    // Additional methods to manage status, retries, etc., will be added as needed.
}
