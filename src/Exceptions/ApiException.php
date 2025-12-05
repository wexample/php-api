<?php

declare(strict_types=1);

namespace SyrtisClient\Exceptions;

use Psr\Http\Message\ResponseInterface;

use function sprintf;
use function substr;
use function trim;

final class ApiException extends \RuntimeException
{
    public static function fromResponse(ResponseInterface $response, ?\Throwable $previous = null): self
    {
        $statusCode = $response->getStatusCode();
        $body = trim((string) $response->getBody());
        $preview = $body === '' ? 'no response body' : substr($body, 0, 200);

        $message = sprintf('API responded with HTTP %d: %s', $statusCode, $preview);

        return new self($message, $statusCode, $previous);
    }
}
