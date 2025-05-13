<?php

declare(strict_types=1);

namespace Flowy\Attribute;

use Attribute;

/**
 * Attribute to define a class as a Workflow Definition.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Workflow
{
    /**
     * @param string $id The unique identifier for the workflow definition.
     * @param string $version The version of the workflow definition.
     * @param string $name A human-readable name for the workflow definition.
     * @param array<string, mixed>|null $initialContextSchema Optional schema for validating initial context (Not used in MVP).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $name,
        public readonly ?array $initialContextSchema = null
    ) {}
}
