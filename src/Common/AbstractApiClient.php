<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

abstract class AbstractApiClient extends Client
{
    public function requestJson(string $method, string $path, array $options = []): array
    {
        return parent::requestJson($method, $path, $options);
    }
}
