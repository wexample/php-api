<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Const;

final class HttpMethod
{
    public const GET     = 'GET';
    public const POST    = 'POST';
    public const PUT     = 'PUT';
    public const PATCH   = 'PATCH';
    public const DELETE  = 'DELETE';
    public const HEAD    = 'HEAD';
    public const OPTIONS = 'OPTIONS';

    private function __construct() {}
}
