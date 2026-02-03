<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use Wexample\PhpApi\Exceptions\ApiException;

abstract class AbstractApiClient extends Client
{
    public function requestJson(string $method, string $path, array $options = []): array
    {
        try {
            return parent::requestJson($method, $path, $options);
        } catch (ApiException $exception) {
            if ($this->isDebugEnabled()) {
                $this->dumpApiException($method, $path, $options, $exception);
            }

            throw $exception;
        }
    }

    protected function isDebugEnabled(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        $debug = $_ENV['PHP_API_DEBUG'] ?? $_SERVER['PHP_API_DEBUG'] ?? '1';

        return $env === 'dev' && $debug !== '0';
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

        $debugPayload = [
            'endpoint' => $this->buildFullUrl($path),
            'method' => strtoupper($method),
            'payload' => $payload,
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ],
            'response' => [
                'data' => $responseData,
                'body' => $responseBody,
            ],
            'curl' => $this->buildCurlCommand($method, $path, $payload),
        ];

        $logPath = sys_get_temp_dir() . '/php_api_debug_' . date('Ymd_His') . '.log';
        file_put_contents(
            $logPath,
            json_encode($debugPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $debugPayload['logFile'] = $logPath;

        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            \Symfony\Component\VarDumper\VarDumper::dump($debugPayload);
        } else {
            var_dump($debugPayload);
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
