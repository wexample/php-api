<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Exceptions;

/**
 * Thrown when an API response envelope ({type, code, data}) is malformed
 * or explicitly carries type "error". The message holds the server error
 * key (e.g. "ERR_INVALID_CREDENTIALS") when available.
 */
final class ApiEnvelopeException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $responseCode = null,
        private readonly ?array $envelope = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function getEnvelope(): ?array
    {
        return $this->envelope;
    }
}
