<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Helper;

use Wexample\Helpers\Helper\TextHelper;
use Wexample\PhpApi\Exceptions\ApiSchemaException;

final class SchemaHelper
{
    public static function getSchemaName(array $schema): string
    {
        return is_string($schema['name'] ?? null) ? $schema['name'] : 'unknown';
    }

    public static function assertAllowedFields(
        array $data,
        array $schema,
        array $extraAllowed = ['secureId']
    ): void {
        $allowed = $extraAllowed;
        $entityName = self::getSchemaName($schema);

        foreach ($schema['properties'] ?? [] as $property) {
            if (! is_array($property)) {
                continue;
            }

            $propertyName = $property['name'] ?? null;
            if (is_string($propertyName) && $propertyName !== '') {
                $allowed[] = $propertyName;
            }
        }

        foreach (array_keys($data) as $field) {
            if (! in_array($field, $allowed, true)) {
                throw ApiSchemaException::fieldNotAllowed($entityName, $field);
            }
        }
    }

    public static function normalizeValue(
        mixed $value,
        string $type,
        bool $nullable,
        string $entityName,
        string $propertyName
    ): mixed {
        if (TextHelper::isNullOrNullString($value)) {
            return null;
        }

        if ($value === '' && $nullable && $type !== 'string') {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float' => (float) $value,
            'bool', 'boolean' => self::normalizeBoolean($value, $nullable),
            'datetime' => self::normalizeDateTimeValue($value, $entityName, $propertyName),
            'string' => (string) $value,
            default => $value,
        };
    }

    private static function normalizeBoolean(mixed $value, bool $nullable): ?bool
    {
        if (TextHelper::isBoolOrBoolString($value)) {
            return TextHelper::parseBooleanOrNull(is_string($value) ? $value : ($value ? 'true' : 'false'));
        }

        if ($nullable) {
            return null;
        }

        return (bool) $value;
    }

    private static function normalizeDateTimeValue(
        mixed $value,
        string $entityName,
        string $propertyName
    ): ?\DateTimeImmutable {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format(\DateTimeInterface::ATOM));
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable $exception) {
            throw ApiSchemaException::invalidDateTime($entityName, $propertyName, $exception);
        }
    }
}
