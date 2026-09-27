<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Const;

/**
 * @deprecated Use \Wexample\PhpApi\Enum\HttpMethod; every client method accepts both.
 */
final class HttpMethod
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const PATCH = 'PATCH';
    public const DELETE = 'DELETE';
    public const HEAD = 'HEAD';
    public const OPTIONS = 'OPTIONS';

    private function __construct()
    {
    }
}
