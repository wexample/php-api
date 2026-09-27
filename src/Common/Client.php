<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use function array_merge;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

use function ltrim;

use Psr\Http\Message\ResponseInterface;

use function rtrim;

use Wexample\PhpApi\Enum\HttpMethod;
use Wexample\PhpApi\Exceptions\ApiException;

/**
 * Generic JSON API client built on top of Guzzle, for any remote service.
 *
 * @example
 * $client = new Client('https://api.example.com', 'api-key-here', options: new ClientOptions(timeout: 30));
 * $response = $client->get('/v1/things', ['query' => ['page' => 1]]);
 */
class Client
{
    /**
     * Sent unless a default or per-request header overrides it; subclasses
     * redefine the constant to identify themselves.
     */
    public const string USER_AGENT = 'wexample-php-api';

    /**
     * Path requested by checkConnection(), relative to the base URL.
     */
    public const string PING_PATH = '';

    private ClientInterface $httpClient;
    private string $baseUrl;
    private ClientOptions $options;
    private ?float $lastRequestAt = null;

    /**
     * @param string $baseUrl Base URL of the API, e.g. https://api.example.com.
     * @param string|null $apiKey Optional API key for Bearer authentication.
     * @param ClientInterface|null $httpClient Custom HTTP client instance (defaults to Guzzle).
     * @param array<string, string> $defaultHeaders Extra headers sent with every request.
     */
    public function __construct(
        string $baseUrl,
        public readonly ?string $apiKey = null,
        ?ClientInterface $httpClient = null,
        private array $defaultHeaders = [],
        ?ClientOptions $options = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->options = $options ?? new ClientOptions();

        $this->httpClient = $httpClient ?? new GuzzleClient([
            'base_uri' => $this->baseUrl,
        ]);

        if ($apiKey !== null) {
            $this->setApiKey($apiKey);
        }
    }

    public function setApiKey(string $apiKey): void
    {
        $this->setBearerToken($apiKey);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getOptions(): ClientOptions
    {
        return $this->options;
    }

    /**
     * Whether the remote answers PING_PATH without an error status. A failure
     * of any kind is the answer, not an error: callers use it for health checks.
     */
    public function checkConnection(): bool
    {
        try {
            $this->request(HttpMethod::GET, static::PING_PATH);
        } catch (ApiException) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setDefaultHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $this->setDefaultHeader($name, $value);
        }
    }

    public function setDefaultHeader(string $name, string $value): void
    {
        $this->defaultHeaders[$name] = $value;
    }

    public function removeDefaultHeader(string $name): void
    {
        unset($this->defaultHeaders[$name]);
    }

    public function setBearerToken(string $token): void
    {
        $this->setDefaultHeader('Authorization', 'Bearer ' . $token);
    }

    protected function requestJson(HttpMethod|string $method, string $path, array $options = []): array
    {
        $response = $this->request($method, $path, $options);

        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ApiException(
                'Invalid JSON response: ' . $e->getMessage(),
                previous: $e
            );
        }

        if (! is_array($data)) {
            throw new ApiException('Unexpected JSON response shape (expected object/array).');
        }

        return $data;
    }

    /**
     * Performs a GET request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function get(string $path, array $options = []): ResponseInterface
    {
        return $this->request(HttpMethod::GET, $path, $options);
    }

    /**
     * Performs a POST request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function post(string $path, array $options = []): ResponseInterface
    {
        return $this->request(HttpMethod::POST, $path, $options);
    }

    /**
     * Performs a PUT request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function put(string $path, array $options = []): ResponseInterface
    {
        return $this->request(HttpMethod::PUT, $path, $options);
    }

    /**
     * Performs a PATCH request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function patch(string $path, array $options = []): ResponseInterface
    {
        return $this->request(HttpMethod::PATCH, $path, $options);
    }

    /**
     * Performs a DELETE request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function delete(string $path, array $options = []): ResponseInterface
    {
        return $this->request(HttpMethod::DELETE, $path, $options);
    }

    /**
     * Sends an HTTP request using the underlying client, retrying transient
     * failures of idempotent methods as ClientOptions allows.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     *
     * @throws ApiException When transport fails or the API replies with an error status.
     */
    public function request(HttpMethod|string $method, string $path, array $options = []): ResponseInterface
    {
        $method = HttpMethod::toValue($method);
        $options = $this->buildRequestOptions($options);
        $uri = ltrim($path, '/');
        // A method outside the enum (WebDAV, custom verbs) is never assumed idempotent.
        $retries = HttpMethod::tryFrom($method)?->isIdempotent() ? $this->options->retries : 0;

        for ($attempt = 0; ; $attempt++) {
            try {
                return $this->send($method, $uri, $options);
            } catch (ApiException $exception) {
                if ($attempt >= $retries || ! $exception->isTransient()) {
                    throw $exception;
                }

                $this->pause($this->options->retryDelay * (2 ** $attempt));
            }
        }
    }

    /**
     * Sleeps between attempts and to honour the rate limit; overridden by
     * tests to observe the delays without waiting.
     */
    protected function pause(float $seconds): void
    {
        usleep((int) round($seconds * 1_000_000));
    }

    private function send(string $method, string $uri, array $options): ResponseInterface
    {
        $this->waitForRateLimit();

        try {
            $response = $this->httpClient->request($method, $uri, $options);
        } catch (GuzzleException $exception) {
            if (
                $exception instanceof \GuzzleHttp\Exception\RequestException
                && $exception->getResponse() instanceof ResponseInterface
            ) {
                throw ApiException::fromResponse($exception->getResponse(), $exception);
            }

            throw ApiException::fromTransportFailure($exception);
        }

        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response);
        }

        return $response;
    }

    private function waitForRateLimit(): void
    {
        $now = microtime(true);

        if ($this->lastRequestAt !== null) {
            $wait = $this->options->rateLimitDelay - ($now - $this->lastRequestAt);

            if ($wait > 0) {
                $this->pause($wait);
                $now += $wait;
            }
        }

        $this->lastRequestAt = $now;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildRequestOptions(array $options): array
    {
        $options['headers'] = $this->buildHeaders($options['headers'] ?? []);

        if ($this->options->timeout > 0) {
            $options['timeout'] ??= $this->options->timeout;
        }

        if ($this->options->connectTimeout > 0) {
            $options['connect_timeout'] ??= $this->options->connectTimeout;
        }

        if (isset($options['multipart'])) {
            // Let Guzzle generate the multipart boundary: a forced
            // Content-Type (e.g. a default application/json header) would
            // corrupt the request body declaration.
            foreach (array_keys($options['headers']) as $name) {
                if (strtolower($name) === 'content-type') {
                    unset($options['headers'][$name]);
                }
            }
        }

        return $options;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function buildHeaders(array $headers): array
    {
        return array_merge(
            [
                'User-Agent' => static::USER_AGENT,
                'Accept' => 'application/json',
            ],
            $this->defaultHeaders,
            $headers,
        );
    }
}
