# php_api

Version: 4.0.2

`wexample/php-api` is a PHP client library for talking to JSON APIs: a Guzzle-backed `Client` that prefixes a base URL, sends a `Authorization: Bearer` header on every call, and turns any response with a status of 400 or more into an `ApiException` carrying the decoded body. On top of it, src/Common/AbstractApiEntitiesClient.php adds an entity layer — repositories that unwrap the `{type, code, message?, data}` envelope produced by `wexample/symfony-api` controllers, check each item against the entity schema the client exposes, and return hydrated `AbstractApiEntity` objects with their relationships resolved instead of nested arrays.

It is for PHP applications consuming a Wexample-style API, whether they only need the plain HTTP verbs and multipart uploads of src/Common/AbstractApiClient.php or the full schema-driven entity mapping.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The package is one inheritance chain of three client classes plus an entity layer hanging off the last of them. Everything lives under `Wexample\PhpApi\` (PSR-4 on `src/`, declared in composer.json), split into `Common/` (the classes an application extends), `Helper/` (stateless parsing), `Exceptions/` and `Const/`.

### The three client layers

src/Common/Client.php is the transport. It normalises the base URL (`$this->baseUrl = rtrim($baseUrl, '/') . '/'`), builds a Guzzle client on it unless one is injected, keeps a mutable `$defaultHeaders` map — `setBearerToken()` writes `Authorization: Bearer …` into it — and exposes `get/post/put/patch/delete`, all forwarding to `request()`. That single method merges `User-Agent` and `Accept: application/json` under the default headers under the per-call headers, strips any `content-type` when `$options['multipart']` is set so Guzzle can generate its own boundary, and converts failure into `ApiException`: a `RequestException` carrying a response and any status `>= 400` both go through `ApiException::fromResponse()`.

src/Common/AbstractApiClient.php adds the JSON and file concerns. It widens `requestJson()` to public and wraps it in an optional debug trap: when `setDebugEnabled(true)` and the exception code is outside the 4xx range, `dumpApiException()` prints endpoint, payload, decoded response and a ready-to-paste `curl -i -X …` line — via `VarDumper` if Symfony's is installed, plain JSON otherwise — then `exit(1)`. `requestFormDataFromJson()` is the upload convention: one multipart part named `data` holding `json_encode($data)`, then the files as `upload_0`, `upload_1`, … each accepted as a path, an `SplFileInfo`, an open resource or a raw Guzzle part.

src/Common/AbstractApiEntitiesClient.php is the entity entry point. Its constructor instantiates an `ApiEntityManager` from `$this->getRepositoryClasses()` — the one abstract method a concrete client must implement — and a fresh `ApiEntityRegistry`. `getRepository($entity)` delegates to the manager and accepts either an entity name or an `AbstractApiEntity` class-string.

### The entity layer

src/Common/ApiEntityManager.php owns the entity-name → repository table. At construction it calls `$repositoryClass::getEntityType()` on each declared repository, refuses anything not extending `AbstractApiEntity`, and indexes by `$entityType::getEntityName()`. Repositories are built lazily on first `get()`; an unknown name throws `InvalidArgumentException` listing the registered ones.

src/Common/AbstractApiRepository.php is where nearly all the logic sits: route building, envelope unwrapping, schema validation, hydration and relationship resolution. Each concrete repository only declares `public static function getEntityType(): string`. Routes come from `buildPath()`, which kebab-cases the entity name — `TextHelper::toKebab(static::getEntityName())` — so `fetch()` hits `foo-bar/show/<id>` and `fetchList()` hits `foo-bar/list`.

src/Common/AbstractApiEntity.php is deliberately thin: a `secureId`, plus `metadata`, `relationships`, `values` and `relationshipMap` arrays. `fromArray()` returns `new static()` with nothing populated — the repository fills the object afterwards. Reads go through `__get()` and `__call()`, which look in `values`, then `relationshipMap`, then the relationship list matched by name; `set*()` writes into `values`. Consequence for anyone adding an entity: typed properties are optional, and an entity declaring none still answers `$entity->getTitle()`.

### The path of a call

`$client->getRepository(Article::class)->fetch('abc')` goes:

1. `ApiEntityManager::get()` resolves the name and lazily constructs the repository with the client.
2. `AbstractApiRepository::fetch()` builds `article/show/abc` (`rawurlencode` on the identifier) and calls `$this->client->requestJson(HttpMethod::GET, …)`.
3. `Client::request()` sends it; a `>= 400` status becomes `ApiException`, then the body is decoded and required to be an array.
4. `extractPayload()` hands the decoded response to `ApiEnvelopeHelper::unwrap()`, which throws `ApiEnvelopeException` on `type === 'error'` (message = the server error key, e.g. `ERR_INVALID_CREDENTIALS`) or on a missing `data` key, and returns `$response['data']` otherwise. `fetchList()` adds `extractItems()`, requiring an `items` array inside that payload.
5. `hydrateFromApiItem()` splits the item into `[entity, metadata, relationships]`, rejecting anything without a string `type` and an `entity` object, then `assertApiItemType()` checks `$item['type']` equals this repository's entity name.
6. `createFromApiItem()` hydrates: `fromArray()`, schema lookup, `validateExtraFields()`, `hydrateEntityFields()`, `hydrateEntityIdentifier()`, `setMetadata()`, registration in the registry, then `setRelationships()`.

### Schema-driven hydration

The schema is not stored in this package. `getEntitySchemas()` calls the same method on the client, guarded by `method_exists()` — the abstract client never declares it, so a concrete client missing `getEntitySchemas()` fails at hydration time with `Client must implement getEntitySchemas() to hydrate relationships.` The same holds for `getEntityRegistry()`. A schema is an array keyed by entity name, each holding `properties` entries of `{name, type, nullable, target}`.

Hydration is strict in both directions. `SchemaHelper::assertAllowedFields()` throws on any field the schema does not declare (only `secureId` is whitelisted), and `hydrateEntityFields()` throws `ApiSchemaException::nonNullableNull()` when a non-nullable property arrives as `null`. Values pass through `SchemaHelper::normalizeValue()`, which casts by declared type and turns `datetime` into a `DateTimeImmutable`. Assignment tries the real property first via reflection (`setAccessible(true)`), then the generated setter, then gives up with `ApiSchemaException::propertyNotFound()`.

### Relationships and stubs

`buildRelationshipsForEntity()` walks the schema for properties typed `relation` (one linked entity) or `collection` (many) and resolves each value in `resolveRelationshipEntity()`:

- an inline array is hydrated by the target's own repository, recursively;
- a string found as a key in the item's `relationships` side-load is hydrated the same way;
- a bare string id becomes an `ApiEntityStub` — an `AbstractApiEntity` with `isStub() === true` and a `targetName`.

Stubs are how cycles and forward references stay cheap. src/Common/ApiEntityRegistry.php indexes hydrated entities by snake-cased entity name and `secureId`; `registerStub()` swaps the stub immediately if the real entity is already known, otherwise queues it under a `WeakReference` to the owner, and the next `registerEntity()` with that id calls `$owner->replaceRelationship($stub, $entity)` on everyone waiting. The weak reference is what keeps the registry from pinning entities in memory. Note the registry is per-client and never cleared: it lives as long as the client instance.

Relationships are stored twice — a flat list in `relationships` and a `relationshipMap` keyed by property name — which is why `$article->author` returns a single entity for a `relation` and an array for a `collection`.

### Errors

Three unrelated exception types, by origin: `ApiException` for transport and HTTP status (carries `getResponseBody()` and `getResponseData()`), `ApiEnvelopeException` for a malformed or `type: error` envelope (carries `getResponseCode()` and the full `getEnvelope()`), `ApiSchemaException` for anything that fails hydration. Only the last has a stable error vocabulary — `CODE_UNKNOWN_FIELD`, `CODE_PROPERTY_NOT_FOUND`, `CODE_NON_NULLABLE_NULL`, `CODE_INVALID_VALUE`, `CODE_INVALID_ITEM`, `CODE_UNKNOWN_RELATIONSHIP` — exposed through `getErrorCode()`, with a private constructor and named factories that always report the owning entity and field. Its docblock states the intent: a schema exception means the API contract drifted, and the client is not meant to tolerate it.

One rough edge to know before editing `Client::requestJson()`: its `catch (JsonException $e)` is unqualified inside `namespace Wexample\PhpApi\Common` with no matching `use`, so it resolves to a class that does not exist and the `JSON_THROW_ON_ERROR` failure escapes uncaught.

### Dependencies

composer.json requires only `php: >=7.4` and `guzzlehttp/guzzle: ^7.8` (PSR-7 interfaces arrive with it), yet the entity layer imports `Wexample\Helpers\Helper\ClassHelper`, `Wexample\Helpers\Helper\TextHelper` and `Wexample\Helpers\Class\Traits\HasSnakeShortClassNameClassTrait` — `wexample/helpers` must be on the autoloader for anything beyond the bare `Client`. Symfony's `VarDumper` is probed at runtime with `class_exists()` and is genuinely optional. The declared floor of PHP 7.4 is also below what the code uses: constructor property promotion, `match`, named arguments and typed class constants (`public const string CODE_UNKNOWN_FIELD`) put the real floor at PHP 8.3.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- guzzlehttp/guzzle: ^7.8
- wexample/php-helpers: >=4.0.0

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
