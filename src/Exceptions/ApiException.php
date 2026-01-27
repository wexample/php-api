<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Exceptions;

use Psr\Http\Message\ResponseInterface;

use function sprintf;
use function substr;
use function trim;

final class ApiException extends \RuntimeException
{
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

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }
}
