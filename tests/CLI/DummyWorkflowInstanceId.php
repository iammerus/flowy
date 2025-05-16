<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use Flowy\Model\WorkflowInstanceIdInterface;

class DummyWorkflowInstanceId implements WorkflowInstanceIdInterface
{
    public function toString(): string { return 'dummy-id'; }
    public function equals(WorkflowInstanceIdInterface $other): bool { return $other->toString() === 'dummy-id'; }
    public static function fromString(string $identifier): static { return new static(); }
    public static function generate(): static { return new static(); }
    public function __toString(): string { return $this->toString(); }
}
