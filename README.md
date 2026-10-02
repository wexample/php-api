# php_api

Version: 5.0.1

`wexample/php-api` is a generic PHP client for any JSON API: a Guzzle-backed `Client` that prefixes a base URL, sends an optional `Authorization: Bearer` header, and turns any response with a status of 400 or more — or a request that never got one — into an `ApiException` that says whether retrying may help (`isTransient()`). src/Common/ClientOptions.php adds the transport policy shared with the Python `wexample_api` gateway and the TypeScript `@wexample/js-api` client: timeouts, retries of idempotent requests, a minimum delay between requests, and `checkConnection()` for health checks.

It knows nothing about the remote's payloads. Clients of APIs served by `wexample/symfony-api` — envelope `{type, code, data}`, entity schemas, repositories — build on `wexample/php-api-entity`, which extends this package; a client for a third-party service (Rocket.Chat, Stripe, …) extends src/Common/AbstractApiClient.php directly.

```php
$client = new Client(
    'https://api.example.com',
    'api-key',
    options: new ClientOptions(timeout: 30, retries: 2, rateLimitDelay: 0.5),
);

$client->checkConnection();                       // bool, never throws
$response = $client->get('v1/things', ['query' => ['page' => 1]]);
```

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

Two client classes, one options object, one exception. Everything lives under `Wexample\PhpApi\` (PSR-4 on `src/`, declared in composer.json): `Common/` holds the classes an application builds on, `Enum/` the HTTP method, `Exceptions/` the error. The only runtime dependency is `guzzlehttp/guzzle`; Symfony's `VarDumper` is probed with `class_exists()` and stays optional.

The entity layer that used to live here — repositories, schemas, the `{type, code, data}` envelope — moved to `wexample/php-api-entity`, whose `AbstractApiEntitiesClient` extends `AbstractApiClient`. Nothing in this package knows the shape of a response body.

### The path of a request

`$client->get('things')` goes through src/Common/Client.php:

1. `request()` normalizes the method with `HttpMethod::toValue()`, so both the enum and a raw string (`'delete'`, `'PROPFIND'`) are accepted.
2. `buildRequestOptions()` merges headers — `User-Agent` from `static::USER_AGENT` and `Accept: application/json`, under the client's default headers, under the per-call ones — adds `timeout` and `connect_timeout` from `ClientOptions` unless the call sets its own, and drops any `content-type` when `multipart` is set so Guzzle writes its own boundary.
3. `send()` first waits for the rate limit (`waitForRateLimit()`: the gap since the previous request is topped up to `rateLimitDelay`), then calls Guzzle. A `RequestException` carrying a response and any status `>= 400` become `ApiException::fromResponse()`; a failure with no response (connection refused, DNS, timeout) becomes `ApiException::fromTransportFailure()`.
4. Back in `request()`, a transient `ApiException` is retried up to `retries` times, waiting `retryDelay` seconds doubled at each attempt — only for idempotent methods (`HttpMethod::isIdempotent()`: everything but POST and PATCH), since a retried POST could apply twice on the remote. A method outside the enum is never retried.

Every wait goes through the protected `pause()`, which tests override to record delays instead of sleeping.

### Options

src/Common/ClientOptions.php is a readonly value object passed as the constructor's last argument. Its fields mirror the Python `AbstractGateway` (`timeout`, `rate_limit_delay`, `retries`) and `@wexample/js-api`'s `ApiClientOptions`; every duration is in seconds. Defaults keep the historical behaviour — no timeout, no retry, no pacing — so existing clients are unchanged until they opt in. The options apply to the injected Guzzle client as well, since they are enforced in `Client` rather than in a Guzzle middleware.

`checkConnection()` requests `static::PING_PATH` (empty by default: the base URL) and answers a boolean; a subclass points it at the remote's health route.

### JSON and debugging

`Client::requestJson()` decodes the body with `JSON_THROW_ON_ERROR` and requires an array; both failures become `ApiException`. src/Common/AbstractApiClient.php makes it public and adds two concerns:

- a debug trap: with `setDebugEnabled(true)`, an exception outside the 4xx range is dumped — endpoint, payload, decoded response and a ready-to-paste `curl -i -X …` line — through `VarDumper` when present, as JSON otherwise, then rethrown;
- `requestFormDataFromJson()`, the upload convention shared with `@wexample/js-api`: a multipart part named `data` holding the JSON payload, then the files as `upload_0`, `upload_1`, … each given as a path, an `SplFileInfo`, an open resource or a raw Guzzle part.

### Errors

src/Exceptions/ApiException.php is the only exception. Its code is the HTTP status (0 when no response came back); `getResponseBody()` and `getResponseData()` expose the raw and decoded body. `isTransient()` is the retry contract used by `Client` and by callers such as `symfony-data-sync`: true for a transport failure, any 5xx, 408, 425 and 429; false for other 4xx and for an unreadable response.

### HTTP methods

src/Enum/HttpMethod.php is the backed enum every method accepts. src/Const/HttpMethod.php, the former class of string constants, is kept for callers not yet migrated and marked `@deprecated`: its values are plain strings, which every method still accepts.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- guzzlehttp/guzzle: ^7.8

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
