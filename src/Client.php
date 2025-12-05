<?php

declare(strict_types=1);

namespace Wexample\PhpApi;

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
final class Client
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
        private readonly ?string $apiKey = null,
        ?ClientInterface $httpClient = null,
        private array $defaultHeaders = [],
    ) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';

        $this->httpClient = $httpClient ?? new GuzzleClient([
            'base_uri' => $this->baseUrl,
        ]);

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $this->defaultHeaders['Authorization'] ??= 'Bearer ' . $this->apiKey;
        }
    }

    /**
     * Performs a GET request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function get(string $path, array $options = []): ResponseInterface
    {
        return $this->request('GET', $path, $options);
    }

    /**
     * Performs a POST request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function post(string $path, array $options = []): ResponseInterface
    {
        return $this->request('POST', $path, $options);
    }

    /**
     * Performs a PUT request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function put(string $path, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $path, $options);
    }

    /**
     * Performs a PATCH request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function patch(string $path, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $path, $options);
    }

    /**
     * Performs a DELETE request on the  API.
     *
     * @param array<string, mixed> $options Request options accepted by Guzzle.
     */
    public function delete(string $path, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $path, $options);
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
