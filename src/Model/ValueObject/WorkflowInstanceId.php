<?php

declare(strict_types=1);

namespace Flowy\Model\ValueObject;

use Flowy\Model\WorkflowInstanceIdInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use InvalidArgumentException;

final class WorkflowInstanceId implements WorkflowInstanceIdInterface
{
    private UuidInterface $uuid;

    private function __construct(UuidInterface $uuid)
    {
        $this->uuid = $uuid;
    }

    public static function generate(): static
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $uuidString): static
    {
        if (!Uuid::isValid($uuidString)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid UUID.', $uuidString));
        }
        return new self(Uuid::fromString($uuidString));
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(WorkflowInstanceIdInterface $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->uuid->equals($other->uuid);
    }
}
