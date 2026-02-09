<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use InvalidArgumentException;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\PhpApi\Const\HttpMethod;
use Wexample\PhpApi\Exceptions\ApiSchemaException;
use Wexample\PhpApi\Helper\SchemaHelper;

abstract class AbstractApiRepository
{
    public function __construct(
        protected AbstractApiEntitiesClient $client,
    ) {
    }

    /**
     * @return class-string<AbstractApiEntity>
     */
    abstract public static function getEntityType(): string;

    public static function getEntityName(): string
    {
        $entityType = static::getEntityType();

        if (! is_a($entityType, AbstractApiEntity::class, true)) {
            throw new InvalidArgumentException('Repository entity type must extend AbstractApiEntity.');
        }

        return $entityType::getEntityName();
    }

    /**
     * @return AbstractApiEntity
     */
    protected function createFromApiItem(
        array $data,
        array $metadata = [],
        array $relationships = [],
    ): AbstractApiEntity {
        $entityType = static::getEntityType();

        $entity = $entityType::fromArray($data);
        $schema = $this->getEntitySchema($entityType);
        $this->validateExtraFields($data, $schema);
        $this->hydrateEntityFields($entity, $data, $schema);
        $this->hydrateEntityIdentifier($entity, $data);
        $entity->setMetadata($metadata);
        $this->getEntityRegistry()->registerEntity($entity);
        $entity->setRelationships($this->buildRelationshipsForEntity($entity, $entityType, $data, $relationships));

        return $entity;
    }

    /**
     * @return AbstractApiEntity[]
     */
    protected function createFromApiCollection(array $collection): array
    {
        $output = [];

        foreach ($collection as $item) {
            [$data, $metadata, $relationships] = $this->splitApiItem($item);
            $output[] = $this->createFromApiItem($data, $metadata, $relationships);
        }

        return $output;
    }

    public function hydrateFromApiItem(array $item): AbstractApiEntity
    {
        [$data, $metadata, $relationships] = $this->splitApiItem($item);

        return $this->createFromApiItem($data, $metadata, $relationships);
    }

    protected function buildPath(string $pathSuffix): string
    {
        $entityName = static::getEntityName();
        $entityName = str_replace('_', '-', $entityName);

        return $entityName . '/' . ltrim($pathSuffix, '/');
    }

    public function post(string $endpoint, array $payload = []): array
    {
        return $this->client->requestJson(
            HttpMethod::POST,
            $this->buildPath($endpoint),
            ['json' => $payload]
        );
    }

    /**
     * @return AbstractApiEntity[]
     */
    protected function createRelationships(array $relationships): array
    {
        $output = [];

        foreach ($relationships as $relationship) {
            if (! is_array($relationship)) {
                continue;
            }

            $type = $relationship['type'] ?? null;

            if (! is_string($type) || $type === '') {
                continue;
            }

            $data = $relationship['entity'] ?? $relationship['data'] ?? $relationship;

            if (! is_array($data)) {
                continue;
            }

            unset($data['type']);

            $repository = $this->client->getRepository($type);
            $entityType = $repository::getEntityType();
            $output[] = $entityType::fromArray($data);
        }

        return $output;
    }

    /**
     * @return AbstractApiEntity[]
     */
    protected function buildRelationshipsForEntity(
        AbstractApiEntity $owner,
        string $entityType,
        array $data,
        array $relationships,
    ): array {
        $schema = $this->getEntitySchema($entityType);

        $output = [];
        $relationshipMap = [];

        foreach ($schema['properties'] ?? [] as $property) {
            if (! is_array($property)) {
                continue;
            }

            $propertyName = $property['name'] ?? null;
            if (! is_string($propertyName) || $propertyName === '') {
                throw new \RuntimeException('[php-api] schema property missing name.');
            }

            $type = strtolower((string) ($property['type'] ?? ''));
            if (! in_array($type, ['relation', 'collection'], true)) {
                continue;
            }

            $target = $property['target'] ?? null;
            if (! is_string($target) || $target === '') {
                throw new \RuntimeException('[php-api] schema property missing target.');
            }

            $value = $data[$propertyName] ?? null;

            if ($type === 'relation') {
                $related = $this->resolveRelationshipEntity($owner, $target, $value, $relationships);
                if ($related !== null) {
                    $output[] = $related;
                    $relationshipMap[$propertyName] = [
                        'type' => 'relation',
                        'items' => [$related],
                    ];
                } else {
                    $relationshipMap[$propertyName] = [
                        'type' => 'relation',
                        'items' => [],
                    ];
                }
                continue;
            }

            $items = is_array($value) ? $value : ($value === null ? [] : [$value]);
            $collectionItems = [];
            foreach ($items as $item) {
                $related = $this->resolveRelationshipEntity($owner, $target, $item, $relationships);
                if ($related !== null) {
                    $output[] = $related;
                    $collectionItems[] = $related;
                }
            }

            $relationshipMap[$propertyName] = [
                'type' => 'collection',
                'items' => $collectionItems,
            ];
        }

        if (method_exists($owner, 'setRelationshipMap')) {
            $owner->setRelationshipMap($relationshipMap);
        }

        return $output;
    }

    protected function resolveRelationshipEntity(
        AbstractApiEntity $owner,
        string $target,
        mixed $value,
        array $relationships,
    ): ?AbstractApiEntity {
        if (is_array($value)) {
            [$item, $metadata, $itemRelationships] = $this->splitApiItem($value);

            $targetRepository = $this->client->getRepository($target);
            if ($targetRepository instanceof self) {
                return $targetRepository->createFromApiItem($item, $metadata, $itemRelationships);
            }

            $entityType = $targetRepository::getEntityType();
            return $entityType::fromArray($item);
        }

        if (is_string($value) && $value !== '' && isset($relationships[$value]) && is_array($relationships[$value])) {
            [$item, $metadata, $itemRelationships] = $this->splitApiItem($relationships[$value]);

            $targetRepository = $this->client->getRepository($target);
            if ($targetRepository instanceof self) {
                return $targetRepository->createFromApiItem($item, $metadata, $itemRelationships);
            }

            $entityType = $targetRepository::getEntityType();
            return $entityType::fromArray($item);
        }

        $id = is_string($value) ? $value : null;
        if ($id === null || $id === '') {
            return null;
        }

        $stub = new ApiEntityStub($target, $id);
        $this->getEntityRegistry()->registerStub($owner, $stub);

        return $stub;
    }

    /**
     * @return array<string, array>
     */
    protected function getEntitySchemas(): array
    {
        if (! method_exists($this->client, 'getEntitySchemas')) {
            throw new \RuntimeException('Client must implement getEntitySchemas() to hydrate relationships.');
        }

        /** @var array<string, array> $schemas */
        $schemas = $this->client->getEntitySchemas();

        return $schemas;
    }

    protected function getEntitySchema(string $entityType): array
    {
        $schemas = $this->getEntitySchemas();
        $entityName = $entityType::getEntityName();
        $schema = $schemas[$entityName] ?? null;

        if (! is_array($schema) || ! is_array($schema['properties'] ?? null)) {
            throw new \RuntimeException('[php-api] schema missing or invalid for entity.');
        }

        return $schema;
    }

    protected function hydrateEntityFields(
        AbstractApiEntity $entity,
        array $data,
        array $schema,
    ): void {
        $entityName = SchemaHelper::getSchemaName($schema);

        foreach ($schema['properties'] as $property) {
            if (! is_array($property)) {
                continue;
            }

            $propertyName = $property['name'] ?? null;
            if (! is_string($propertyName) || $propertyName === '') {
                throw new \RuntimeException('[php-api] schema property missing name.');
            }

            $type = strtolower((string) ($property['type'] ?? ''));
            if (in_array($type, ['relation', 'collection'], true)) {
                continue;
            }

            if (! array_key_exists($propertyName, $data)) {
                continue;
            }

            $value = $data[$propertyName];
            $nullable = (bool) ($property['nullable'] ?? false);

            if ($value === null && ! $nullable) {
                throw ApiSchemaException::nonNullableNull($entityName, $propertyName);
            }

            $value = SchemaHelper::normalizeValue($value, $type, $nullable, $entityName, $propertyName);

            $this->assignPropertyValue($entity, $propertyName, $value);
        }
    }

    protected function hydrateEntityIdentifier(
        AbstractApiEntity $entity,
        array $data
    ): void {
        $this->assignPropertyValue($entity, 'secureId', (string) $data['secureId']);
    }

    protected function validateExtraFields(
        array $data,
        array $schema
    ): void {
        SchemaHelper::assertAllowedFields($data, $schema, ['secureId']);
    }

    protected function assignPropertyValue(
        AbstractApiEntity $entity,
        string $propertyName,
        mixed $value
    ): void {
        $reflection = new \ReflectionObject($entity);
        if ($reflection->hasProperty($propertyName)) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($entity, $value);
            return;
        }

        $setter = ClassHelper::buildFieldSetterName($propertyName);
        if (method_exists($entity, $setter) || method_exists($entity, '__call')) {
            $entity->{$setter}($value);
            return;
        }

        throw ApiSchemaException::propertyNotFound($entity::class, $propertyName);
    }

    protected function getEntityRegistry(): ApiEntityRegistry
    {
        if (! method_exists($this->client, 'getEntityRegistry')) {
            throw new \RuntimeException('Client must implement getEntityRegistry() to hydrate relationships.');
        }

        /** @var ApiEntityRegistry $registry */
        $registry = $this->client->getEntityRegistry();

        return $registry;
    }

    /**
     * @return array{0: array, 1: array, 2: array}
     */
    protected function splitApiItem(array $item): array
    {
        $data = is_array($item['entity'] ?? null) ? $item['entity'] : $item;
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $relationships = is_array($item['relationships'] ?? null) ? $item['relationships'] : [];

        return [$data, $metadata, $relationships];
    }

    /**
     * @return AbstractApiEntity[]
     */
    public function fetchList(
        array $query = [],
        ?int $page = null,
        ?int $length = null,
        string $endpoint = 'list',
    ): array {
        if ($page !== null) {
            $query['page'] = $page;
        }

        if ($length !== null) {
            $query['length'] = $length;
        }

        $data = $this->client->requestJson(
            HttpMethod::GET,
            $this->buildPath($endpoint),
            [
                'query' => $query,
            ]
        );

        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        return $this->createFromApiCollection($items);
    }

    public function fetch(string $identifier, string $endpoint = 'show'): AbstractApiEntity
    {
        $data = $this->client->requestJson(
            HttpMethod::GET,
            $this->buildPath($endpoint . '/' . rawurlencode($identifier))
        );

        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;
        [$item, $metadata, $relationships] = $this->splitApiItem($payload);

        return $this->createFromApiItem($item, $metadata, $relationships);
    }
}
