<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Exceptions;

use RuntimeException;

final class ApiSchemaException extends RuntimeException
{
    public static function fieldNotAllowed(string $entityName, string $field): self
    {
        return new self(sprintf(
            '[php-api] field not allowed by schema (%s): %s',
            $entityName,
            $field
        ));
    }

    public static function propertyNotFound(string $entityClass, string $property): self
    {
        return new self(sprintf(
            '[php-api] property not found on entity (%s): %s',
            $entityClass,
            $property
        ));
    }

    public static function nonNullableNull(string $entityName, string $property): self
    {
        return new self(sprintf(
            '[php-api] non-nullable property is null (%s): %s',
            $entityName,
            $property
        ));
    }

    public static function invalidDateTime(string $entityName, string $property, ?\Throwable $previous = null): self
    {
        return new self(sprintf(
            '[php-api] invalid datetime (%s): %s',
            $entityName,
            $property
        ), 0, $previous);
    }
}
