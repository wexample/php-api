<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use Wexample\PhpApi\Const\HttpMethod;
use Wexample\PhpApi\Exceptions\ApiException;

abstract class AbstractApiClient extends Client
{
    protected bool $debugEnabled = false;

    public function requestJson(string $method, string $path, array $options = []): array
    {
        try {
            return parent::requestJson($method, $path, $options);
        } catch (ApiException $exception) {
            if ($this->isDebugEnabled() && $this->shouldDumpApiException($exception)) {
                $this->dumpApiException($method, $path, $options, $exception);
            }

            throw $exception;
        }
    }

    /**
     * Posts a multipart request holding a "data" JSON field plus files as
     * upload_0, upload_1, ... — same convention as js-api's
     * requestFormDataFromJson.
     *
     * @param array<int, \SplFileInfo|resource|string|array{contents: mixed, filename?: string}> $files
     *        Each file may be a path, an SplFileInfo, an open resource, or a
     *        Guzzle multipart part (without "name").
     */
    public function requestFormDataFromJson(
        string $path,
        array $data,
        array $files = [],
        string $fileKeyPrefix = 'upload_',
    ): array {
        $multipart = [
            [
                'name' => 'data',
                'contents' => json_encode($data, JSON_THROW_ON_ERROR),
            ],
        ];

        foreach (array_values($files) as $index => $file) {
            $multipart[] = $this->buildMultipartFilePart($fileKeyPrefix . $index, $file);
        }

        return $this->requestJson(
            HttpMethod::POST,
            $path,
            ['multipart' => $multipart]
        );
    }

    /**
     * @param \SplFileInfo|resource|string|array{contents: mixed, filename?: string} $file
     */
    protected function buildMultipartFilePart(string $name, mixed $file): array
    {
        if ($file instanceof \SplFileInfo) {
            return [
                'name' => $name,
                'contents' => fopen($file->getPathname(), 'rb'),
                'filename' => $file->getFilename(),
            ];
        }

        if (is_array($file)) {
            return ['name' => $name] + $file;
        }

        if (is_resource($file)) {
            return [
                'name' => $name,
                'contents' => $file,
            ];
        }

        return [
            'name' => $name,
            'contents' => fopen((string) $file, 'rb'),
            'filename' => basename((string) $file),
        ];
    }

    public function setDebugEnabled(bool $enabled): void
    {
        $this->debugEnabled = $enabled;
    }

    protected function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }

    protected function shouldDumpApiException(ApiException $exception): bool
    {
        $code = $exception->getCode();

        // Keep normal application flow for expected client-side API responses.
        return $code < 400 || $code >= 500;
    }

    protected function dumpApiException(
        string $method,
        string $path,
        array $options,
        ApiException $exception
    ): void {
        $payload = $options['json'] ?? null;
        $responseData = $exception->getResponseData();
        $responseBody = $exception->getResponseBody();
        $decodedBody = null;

        if ($responseData === null && is_string($responseBody) && $responseBody !== '') {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded)) {
                $decodedBody = $decoded;
            }
        }

        $debugPayload = [
            'endpoint' => $this->buildFullUrl($path),
            'method' => strtoupper($method),
            'payload' => $payload,
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'responseBody' => $responseBody,
                'responseData' => $responseData,
                'responseDecoded' => $decodedBody,
            ],
            'response' => [
                'data' => $responseData,
                'body' => $responseBody,
            ],
            'curl' => $this->buildCurlCommand($method, $path, $payload),
        ];

        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            \Symfony\Component\VarDumper\VarDumper::dump($debugPayload);
        } else {
            if (! headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }

            $encoded = json_encode($debugPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            echo $encoded !== false ? $encoded : var_export($debugPayload, true);
        }

        exit(1);
    }

    protected function buildFullUrl(string $path): string
    {
        return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    protected function buildCurlCommand(string $method, string $path, mixed $payload): string
    {
        $endpoint = $this->buildFullUrl($path);
        $method = strtoupper($method);
        $headers = $this->getDefaultHeaders();

        $headerParts = [];
        foreach ($headers as $name => $value) {
            $headerParts[] = sprintf("-H '%s: %s'", $name, $value);
        }

        $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_SLASHES) : '';
        $dataPart = $json !== '' ? sprintf(" -d '%s'", $json) : '';

        return trim(sprintf(
            "curl -i -X %s '%s' %s%s",
            $method,
            $endpoint,
            implode(' ', $headerParts),
            $dataPart
        ));
    }
}
