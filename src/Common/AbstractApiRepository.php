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
    protected function createFromApiItem(array $data): AbstractApiEntity
    {
        $entityType = static::getEntityType();

        return $entityType::fromArray($data);
    }

    /**
     * @return AbstractApiEntity[]
     */
    protected function createFromApiCollection(array $collection): array
    {
        $output = [];

        foreach ($collection as $data) {
            $output[] = $this->createFromApiItem($data);
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
}
