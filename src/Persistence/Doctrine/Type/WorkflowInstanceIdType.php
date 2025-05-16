<?php

declare(strict_types=1);

namespace Flowy\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GuidType;
use Flowy\Model\ValueObject\WorkflowInstanceId; // Updated import
use Flowy\Model\WorkflowInstanceIdInterface;

class WorkflowInstanceIdType extends GuidType
{
    public const NAME = 'workflow_instance_id';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WorkflowInstanceIdInterface
    {
        if ($value === null || $value instanceof WorkflowInstanceIdInterface) {
            return $value;
        }

        if (!is_string($value)) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                $this->getName(),
                ['null', 'string', WorkflowInstanceIdInterface::class]
            );
        }

        try {
            // Use the concrete WorkflowInstanceId class to convert from string
            return WorkflowInstanceId::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailed($value, $this->getName(), $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof WorkflowInstanceIdInterface) {
            // Assuming your WorkflowInstanceIdInterface has a method like toString() or __toString()
            return $value->toString();
        }

        throw ConversionException::conversionFailedInvalidType(
            $value,
            $this->getName(),
            ['null', WorkflowInstanceIdInterface::class]
        );
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // Fallback to the parent GUID type declaration (usually UUID or CHAR(36))
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true; // Ensures the type is commented in the schema if the platform supports it.
    }
}
