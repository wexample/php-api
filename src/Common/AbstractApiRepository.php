<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use InvalidArgumentException;
use Wexample\PhpApi\Const\HttpMethod;

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
    ): AbstractApiEntity
    {
        $entityType = static::getEntityType();

        $entity = $entityType::fromArray($data);
        $entity->setMetadata($metadata);
        $entity->setRelationships($this->createRelationships($relationships));

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

    protected function buildPath(string $pathSuffix): string
    {
        return static::getEntityName() . '/' . ltrim($pathSuffix, '/');
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
