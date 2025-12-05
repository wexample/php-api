<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use Wexample\PhpApi\Const\HttpMethod;
use function array_merge;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

use function ltrim;

use Psr\Http\Message\ResponseInterface;

use function rtrim;

use Wexample\PhpApi\Exceptions\ApiException;

/**
 * Minimal  API client built on top of Guzzle.
 *
 * @example
 * $client = new Client('https://api.wexample.com', 'api-key-here');
 * $response = $client->get('/v1/things', ['query' => ['page' => 1]]);
 */
class Client
{
    private ClientInterface $httpClient;
    private string $baseUrl;

    /**
     * @param string $baseUrl Base URL for the  API, e.g. https://api.syrtis.ai.
     * @param string|null $apiKey Optional API key for Bearer authentication.
     * @param ClientInterface|null $httpClient Custom HTTP client instance (defaults to Guzzle).
     * @param array<string, string> $defaultHeaders Extra headers sent with every request.
     */
    public function __construct(
        string $baseUrl,
        readonly ?string $apiKey = null,
        ?ClientInterface $httpClient = null,
        private array $defaultHeaders = [],
    ) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';

        $this->httpClient = $httpClient ?? new GuzzleClient([
            'base_uri' => $this->baseUrl,
        ]);

        $this->setApiKey($apiKey);
    }

    public function setApiKey(string $apiKey)
    {
        $this->setBearerToken($apiKey);
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

    protected function requestJson(string $method, string $path, array $options = []): array
    {
        $response = $this->request($method, $path, $options);

        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \Wexample\PhpApi\Exceptions\ApiException(
                'Invalid JSON response: ' . $e->getMessage(),
                previous: $e
            );
        }

        if (!is_array($data)) {
            throw new \Wexample\PhpApi\Exceptions\ApiException('Unexpected JSON response shape (expected object/array).');
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
     * Sends an HTTP request using the underlying client.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     *
     * @throws ApiException When transport fails or the API replies with an error status.
     */
    public function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $options['headers'] = $this->buildHeaders($options['headers'] ?? []);

        $uri = ltrim($path, '/');

        try {
            $response = $this->httpClient->request($method, $uri, $options);
        } catch (GuzzleException $exception) {
            throw new ApiException('HTTP request failed: ' . $exception->getMessage(), previous: $exception);
        }

        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response);
        }

        return $response;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function buildHeaders(array $headers): array
    {
        return array_merge(
            [
                'User-Agent' => 'syrtis-client-php/0.1',
                'Accept' => 'application/json',
            ],
            $this->defaultHeaders,
            $headers,
        );
    }
}
