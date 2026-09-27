<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Exceptions;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

use function sprintf;
use function substr;
use function trim;

final class ApiException extends \RuntimeException
{
    /**
     * Statuses a later identical request may not get: timeout, too early,
     * rate limited. Every 5xx is transient too.
     */
    private const array TRANSIENT_STATUS_CODES = [408, 425, 429];

    private bool $transportFailure = false;

    private ?string $responseBody = null;
    private ?array $responseData = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $responseBody = null,
        ?array $responseData = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
        $this->responseData = $responseData;
    }

    public static function fromResponse(ResponseInterface $response, ?\Throwable $previous = null): self
    {
        $statusCode = $response->getStatusCode();
        $body = trim((string) $response->getBody());
        $preview = $body === '' ? 'no response body' : substr($body, 0, 200);

        $message = sprintf('API responded with HTTP %d: %s', $statusCode, $preview);

        $data = null;
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return new self(
            $message,
            $statusCode,
            $previous,
            $body !== '' ? $body : null,
            $data
        );
    }

    /**
     * No response came back: connection refused, DNS failure, timeout.
     */
    public static function fromTransportFailure(GuzzleException $exception): self
    {
        $apiException = new self('HTTP request failed: ' . $exception->getMessage(), previous: $exception);
        $apiException->transportFailure = true;

        return $apiException;
    }

    /**
     * Whether retrying the same request later may succeed, as opposed to a
     * request the remote rejected (4xx) or a response we cannot read.
     */
    public function isTransient(): bool
    {
        $statusCode = $this->getCode();

        return $this->transportFailure
            || $statusCode >= 500
            || in_array($statusCode, self::TRANSIENT_STATUS_CODES, true);
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }
}
