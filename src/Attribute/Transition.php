<?php

declare(strict_types=1);

namespace Flowy\Attribute;

use Attribute;

/**
 * Attribute to define a Transition from a Step to another Step.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Transition
{
    /**
     * @param string $to The ID of the target step to transition to.
     * @param string|null $condition The FQCN or service ID of a ConditionInterface implementation, or an expression string (Phase 2).
     * @param string|null $event The name of an event that can trigger this transition (Phase 2).
     */
    public function __construct(
        public readonly string $to,
        public readonly ?string $condition = null,
        public readonly ?string $event = null
    ) {}
}
