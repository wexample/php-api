<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Exceptions;

use RuntimeException;

/**
 * Thrown when data crossing the client boundary does not match the entity
 * schema: unknown field, malformed API item, unregistered relationship type,
 * invalid value. Clients are strict by design — this exception surfacing
 * means the API contract drifted, not that the client should tolerate it.
 */
final class ApiSchemaException extends RuntimeException
{
    public const string CODE_UNKNOWN_FIELD = 'ERR_SCHEMA_UNKNOWN_FIELD';
    public const string CODE_PROPERTY_NOT_FOUND = 'ERR_SCHEMA_PROPERTY_NOT_FOUND';
    public const string CODE_NON_NULLABLE_NULL = 'ERR_SCHEMA_NON_NULLABLE_NULL';
    public const string CODE_INVALID_VALUE = 'ERR_SCHEMA_INVALID_VALUE';
    public const string CODE_INVALID_ITEM = 'ERR_SCHEMA_INVALID_ITEM';
    public const string CODE_UNKNOWN_RELATIONSHIP = 'ERR_SCHEMA_UNKNOWN_RELATIONSHIP';

    private function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly ?string $entityName = null,
        private readonly ?string $field = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public static function fieldNotAllowed(string $entityName, string $field): self
    {
        return new self(
            sprintf('[php-api] field not allowed by schema (%s): %s', $entityName, $field),
            self::CODE_UNKNOWN_FIELD,
            $entityName,
            $field
        );
    }

    public static function propertyNotFound(string $entityClass, string $property): self
    {
        return new self(
            sprintf('[php-api] property not found on entity (%s): %s', $entityClass, $property),
            self::CODE_PROPERTY_NOT_FOUND,
            $entityClass,
            $property
        );
    }

    public static function nonNullableNull(string $entityName, string $property): self
    {
        return new self(
            sprintf('[php-api] non-nullable property is null (%s): %s', $entityName, $property),
            self::CODE_NON_NULLABLE_NULL,
            $entityName,
            $property
        );
    }

    public static function invalidDateTime(string $entityName, string $property, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('[php-api] invalid datetime (%s): %s', $entityName, $property),
            self::CODE_INVALID_VALUE,
            $entityName,
            $property,
            $previous
        );
    }

    public static function invalidItem(string $entityName, string $reason): self
    {
        return new self(
            sprintf('[php-api] invalid API item for entity "%s": %s.', $entityName, $reason),
            self::CODE_INVALID_ITEM,
            $entityName
        );
    }

    public static function itemTypeMismatch(string $expectedType, string $actualType): self
    {
        return new self(
            sprintf('[php-api] API item type mismatch: expected "%s", got "%s".', $expectedType, $actualType),
            self::CODE_INVALID_ITEM,
            $expectedType
        );
    }

    public static function unknownRelationship(
        string $ownerEntityName,
        string $relationName,
        string $type,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            sprintf(
                '[php-api] cannot hydrate relationship "%s" of entity "%s": no repository registered for type "%s".',
                $relationName,
                $ownerEntityName,
                $type
            ),
            self::CODE_UNKNOWN_RELATIONSHIP,
            $ownerEntityName,
            $relationName,
            $previous
        );
    }
}
