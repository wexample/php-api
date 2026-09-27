<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Enum;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';

    /**
     * Normalizes a method given either as this enum or as a raw string, the
     * form used by callers written before the enum existed.
     */
    public static function toValue(self|string $method): string
    {
        return $method instanceof self ? $method->value : strtoupper($method);
    }
}
