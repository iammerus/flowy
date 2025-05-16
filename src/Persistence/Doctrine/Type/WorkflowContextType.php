<?php

declare(strict_types=1);

namespace Flowy\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Flowy\Context\WorkflowContext;

/**
 * Custom Doctrine type for WorkflowContext objects.
 *
 * This type handles serialization and deserialization of WorkflowContext objects for database storage.
 */
class WorkflowContextType extends Type
{
    /**
     * The name of this type.
     */
    public const NAME = 'workflow_context';

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof WorkflowContext) {
            throw new \InvalidArgumentException(sprintf(
                'Expected instance of %s, got %s instead.',
                WorkflowContext::class,
                get_debug_type($value)
            ));
        }

        // Serialize to JSON
        return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?WorkflowContext
    {
        if ($value === null || $value === '') {
            return new WorkflowContext([]);
        }

        $contextData = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        
        return new WorkflowContext($contextData);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}